<?php

namespace App\Services;

use App\Models\LegacyAppGrantEvent;
use App\Models\LegacyAppGrantSet;
use App\Models\LegacyAppGrantSetApplicationEvent;
use App\Models\User;
use App\Models\UserLegacyAppGrant;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LegacyAppGrantService
{
    public function __construct(
        private readonly PlatformAdministratorService $platformAdministrators,
        private readonly LegacyAppGrantSetService $grantSets,
    ) {}

    /**
     * Grant a legacy application to a user.
     */
    public function grant(
        User $target,
        string $legacyAppId,
        User $actor,
        array $metadata = [],
    ): UserLegacyAppGrant {
        $this->authorizeActor($actor);

        $app = DB::connection('concord_platform')
            ->table('legacy_apps')
            ->where('id', $legacyAppId)
            ->first(['id', 'is_active']);

        if ($app === null) {
            throw ValidationException::withMessages([
                'legacy_app_id' => [
                    'The selected application does not exist or is inactive.',
                ],
            ]);
        }

        return DB::transaction(function () use (
            $target,
            $legacyAppId,
            $actor,
            $metadata,
        ): UserLegacyAppGrant {
            $existingGrant = UserLegacyAppGrant::query()
                ->where('user_id', $target->id)
                ->where('legacy_app_id', $legacyAppId)
                ->whereNull('revoked_at')
                ->exists();

            if ($existingGrant) {
                throw ValidationException::withMessages([
                    'legacy_app_id' => [
                        'This user already has an active grant for the selected application.',
                    ],
                ]);
            }

            $now = now();

            $grant = UserLegacyAppGrant::query()->create([
                'user_id' => $target->id,
                'legacy_app_id' => $legacyAppId,
                'granted_at' => $now,
                'granted_by' => $actor->id,
            ]);

            LegacyAppGrantEvent::query()->create([
                'grant_id' => $grant->id,
                'event_type' => LegacyAppGrantEvent::EVENT_GRANTED,
                'actor_id' => $actor->id,
                'source' => LegacyAppGrantEvent::SOURCE_INDIVIDUAL,
                'metadata' => $metadata ?: null,
                'occurred_at' => $now,
            ]);

            Audit::log(
                'access.legacy_app_granted',
                $actor->id,
                null,
                entityType: 'legacy_app_grant',
                entityId: $grant->id,
                meta: [
                    'target_user_id' => $target->id,
                    'legacy_app_id' => $legacyAppId,
                    'source' => LegacyAppGrantEvent::SOURCE_INDIVIDUAL,
                ],
            );

            return $grant->fresh(['user', 'grantedBy', 'events']);
        });
    }

    /**
     * Assign one Grant Set or a list of individual applications to multiple users.
     *
     * The entire batch is atomic and never revokes existing access.
     */
    public function batchAssign(
        array $userIds,
        string $method,
        User $actor,
        ?string $grantSetId = null,
        array $legacyAppIds = [],
    ): array {
        $this->authorizeActor($actor);

        $userIds = array_values(array_unique($userIds));
        $legacyAppIds = array_values(array_unique($legacyAppIds));

        if ($userIds === []) {
            throw ValidationException::withMessages([
                'user_ids' => ['Select at least one user.'],
            ]);
        }

        if (! in_array($method, ['grant_set', 'individual_apps'], true)) {
            throw ValidationException::withMessages([
                'method' => ['The selected assignment method is invalid.'],
            ]);
        }

        if (
            ($method === 'grant_set' && ($grantSetId === null || $legacyAppIds !== [])) ||
            ($method === 'individual_apps' && ($grantSetId !== null || $legacyAppIds === []))
        ) {
            throw ValidationException::withMessages([
                'method' => [
                    'Provide exactly one assignment method and its corresponding selection.',
                ],
            ]);
        }

        return DB::transaction(function () use (
            $userIds,
            $method,
            $actor,
            $grantSetId,
            $legacyAppIds,
        ): array {
            $targets = User::query()
                ->whereIn('id', $userIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($targets->count() !== count($userIds)) {
                throw ValidationException::withMessages([
                    'user_ids' => ['One or more selected users do not exist.'],
                ]);
            }

            $batchId = (string) Str::ulid();
            $results = [];

            if ($method === 'grant_set') {
                $grantSet = LegacyAppGrantSet::query()
                    ->whereKey($grantSetId)
                    ->first();

                if ($grantSet === null) {
                    throw ValidationException::withMessages([
                        'grant_set_id' => [
                            'The selected Grant Set does not exist or is archived.',
                        ],
                    ]);
                }

                foreach ($targets as $target) {
                    $application = $this->grantSets->apply(
                        $grantSet,
                        $target,
                        $actor,
                    );

                    $results[] = [
                        'user_id' => $target->id,
                        'application_event_id' => $application->id,
                    ];
                }

                return [
                    'batch_id' => $batchId,
                    'method' => $method,
                    'users' => $results,
                ];
            }

            $activeApps = DB::connection('concord_platform')
                ->table('legacy_apps')
                ->whereIn('id', $legacyAppIds)
                ->where('is_active', true)
                ->get(['id'])
                ->keyBy('id');

            if ($activeApps->count() !== count($legacyAppIds)) {
                throw ValidationException::withMessages([
                    'legacy_app_ids' => [
                        'One or more selected applications do not exist or are inactive.',
                    ],
                ]);
            }

            $now = now();

            foreach ($targets as $target) {
                $existingActiveIds = UserLegacyAppGrant::query()
                    ->where('user_id', $target->id)
                    ->whereIn('legacy_app_id', $legacyAppIds)
                    ->whereNull('revoked_at')
                    ->lockForUpdate()
                    ->pluck('legacy_app_id')
                    ->map(fn ($id) => (string) $id)
                    ->all();

                $existingActiveIds = array_flip($existingActiveIds);
                $grantedAppIds = [];
                $skippedAppIds = [];

                foreach ($legacyAppIds as $legacyAppId) {
                    if (isset($existingActiveIds[$legacyAppId])) {
                        $skippedAppIds[] = $legacyAppId;
                        continue;
                    }

                    $grant = UserLegacyAppGrant::query()->create([
                        'user_id' => $target->id,
                        'legacy_app_id' => $legacyAppId,
                        'granted_at' => $now,
                        'granted_by' => $actor->id,
                    ]);

                    LegacyAppGrantEvent::query()->create([
                        'grant_id' => $grant->id,
                        'event_type' => LegacyAppGrantEvent::EVENT_GRANTED,
                        'actor_id' => $actor->id,
                        'source' => LegacyAppGrantEvent::SOURCE_BATCH,
                        'metadata' => [
                            'batch_id' => $batchId,
                            'method' => $method,
                        ],
                        'occurred_at' => $now,
                    ]);

                    Audit::log(
                        'access.legacy_app_granted',
                        $actor->id,
                        null,
                        entityType: 'legacy_app_grant',
                        entityId: $grant->id,
                        meta: [
                            'target_user_id' => $target->id,
                            'legacy_app_id' => $legacyAppId,
                            'source' => LegacyAppGrantEvent::SOURCE_BATCH,
                            'batch_id' => $batchId,
                        ],
                    );

                    $grantedAppIds[] = $legacyAppId;
                }

                $results[] = [
                    'user_id' => $target->id,
                    'granted_app_ids' => $grantedAppIds,
                    'skipped_app_ids' => $skippedAppIds,
                ];
            }

            return [
                'batch_id' => $batchId,
                'method' => $method,
                'users' => $results,
            ];
        });
    }

    /**
     * Revoke an active grant while preserving its historical period.
     */
    public function revoke(
        UserLegacyAppGrant $grant,
        User $actor,
        array $metadata = [],
    ): UserLegacyAppGrant {
        $this->authorizeActor($actor);

        return DB::transaction(function () use (
            $grant,
            $actor,
            $metadata,
        ): UserLegacyAppGrant {
            $grant = UserLegacyAppGrant::query()
                ->whereKey($grant->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $grant->isActive()) {
                throw ValidationException::withMessages([
                    'grant' => [
                        'This application grant has already been revoked.',
                    ],
                ]);
            }

            $now = now();

            $grant->update([
                'revoked_at' => $now,
                'revoked_by' => $actor->id,
            ]);

            LegacyAppGrantEvent::query()->create([
                'grant_id' => $grant->id,
                'event_type' => LegacyAppGrantEvent::EVENT_REVOKED,
                'actor_id' => $actor->id,
                'source' => LegacyAppGrantEvent::SOURCE_INDIVIDUAL,
                'metadata' => $metadata ?: null,
                'occurred_at' => $now,
            ]);

            Audit::log(
                'access.legacy_app_revoked',
                $actor->id,
                null,
                entityType: 'legacy_app_grant',
                entityId: $grant->id,
                meta: [
                    'target_user_id' => $grant->user_id,
                    'legacy_app_id' => $grant->legacy_app_id,
                    'source' => LegacyAppGrantEvent::SOURCE_INDIVIDUAL,
                ],
            );

            return $grant->fresh([
                'user',
                'grantedBy',
                'revokedBy',
                'events',
            ]);
        });
    }

    /**
     * Retrieve a user's current application access and Grant Set history.
     */
    public function getUserAccess(
        User $target,
        User $actor,
    ): array {
        $this->authorizeActor($actor);

        $grants = UserLegacyAppGrant::query()
            ->where('user_id', $target->id)
            ->whereNull('revoked_at')
            ->with([
                'events' => fn ($query) => $query
                    ->orderByDesc('occurred_at'),
            ])
            ->get();

        $apps = DB::connection('concord_platform')
            ->table('legacy_apps')
            ->whereIn('id', $grants->pluck('legacy_app_id'))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'url',
                'icon',
                'description',
            ])
            ->keyBy('id');

        $currentApplications = $grants
            ->filter(fn ($grant) => $apps->has($grant->legacy_app_id))
            ->map(function ($grant) use ($apps) {
                $app = $apps->get($grant->legacy_app_id);
                $latestEvent = $grant->events->first();

                return [
                    'id' => $app->id,
                    'name' => $app->name,
                    'url' => $app->url,
                    'icon' => $app->icon,
                    'description' => $app->description,
                    'granted_at' => $grant->granted_at,
                    'grant_source' => $latestEvent?->source,
                ];
            })
            ->values();

        $grantSetHistory = LegacyAppGrantSetApplicationEvent::query()
            ->where('user_id', $target->id)
            ->with([
                'grantSet' => fn ($query) => $query->withTrashed(),
            ])
            ->orderBy('occurred_at')
            ->get()
            ->groupBy('grant_set_id')
            ->map(function ($events) {
                $first = $events->first();
                $last = $events->last();

                return [
                    'grant_set_id' => $first->grant_set_id,
                    'name' => $first->grantSet?->name,
                    'first_applied_at' => $first->occurred_at,
                    'last_applied_at' => $last->occurred_at,
                ];
            })
            ->values();

        return [
            'applications' => $currentApplications,
            'grant_sets' => $grantSetHistory,
        ];
    }

    private function authorizeActor(User $actor): void
    {
        if (! $this->platformAdministrators->isDesignated($actor->id)) {
            throw new AuthorizationException(
                'Only the Concord Administrator can manage legacy application grants.'
            );
        }
    }
}