<?php

namespace App\Services;

use App\Models\LegacyAppGrantEvent;
use App\Models\LegacyAppGrantSet;
use App\Models\LegacyAppGrantSetApplicationEvent;
use App\Models\User;
use App\Models\UserLegacyAppGrant;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LegacyAppGrantSetService
{
    public function __construct(
        private readonly PlatformAdministratorService $platformAdministrators,
        private readonly AccessService $access,
    ) {}

    /**
     * List Grant Sets.
     * Archived records are included only when requested.
     */
    public function list(
        User $actor,
        bool $includeArchived = false,
    ): Collection {
        $this->authorizeActor($actor);

        $query = LegacyAppGrantSet::query()
            ->with('items')
            ->orderBy('name');

        if ($includeArchived) {
            $query->withTrashed();
        }

        return $query->get();
    }

    /**
     * Retrieve a Grant Set.
     */
    public function find(
        string $grantSetId,
        User $actor,
        bool $includeArchived = false,
    ): LegacyAppGrantSet {
        $this->authorizeActor($actor);

        $query = LegacyAppGrantSet::query()->with('items');

        if ($includeArchived) {
            $query->withTrashed();
        }

        return $query->findOrFail($grantSetId);
    }

    /**
     * Create a Grant Set with its selected applications.
     */
    public function create(
        array $data,
        User $actor,
    ): LegacyAppGrantSet {
        $this->authorizeActor($actor);

        $legacyAppIds = $this->validateLegacyAppIds(
            $data['legacy_app_ids'] ?? [],
        );

        return DB::transaction(function () use (
            $data,
            $legacyAppIds,
            $actor,
        ): LegacyAppGrantSet {
            $grantSet = LegacyAppGrantSet::query()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            foreach ($legacyAppIds as $legacyAppId) {
                $grantSet->items()->create([
                    'legacy_app_id' => $legacyAppId,
                ]);
            }

            Audit::log(
                'access.legacy_app_grant_set_created',
                $actor->id,
                null,
                'legacy_app_grant_set',
                $grantSet->id,
                [
                    'name' => $grantSet->name,
                    'legacy_app_ids' => $legacyAppIds,
                ],
            );

            return $grantSet->fresh('items');
        });
    }

    /**
     * Update Grant Set details and application membership.
     *
     * This only changes the reusable template.
     * It does not modify existing user grants.
     */
    public function update(
        LegacyAppGrantSet $grantSet,
        array $data,
        User $actor,
    ): LegacyAppGrantSet {
        $this->authorizeActor($actor);

        $legacyAppIds = array_key_exists('legacy_app_ids', $data)
            ? $this->validateLegacyAppIds($data['legacy_app_ids'])
            : null;

        return DB::transaction(function () use (
            $grantSet,
            $data,
            $legacyAppIds,
            $actor,
        ): LegacyAppGrantSet {
            $grantSet = LegacyAppGrantSet::query()
                ->whereKey($grantSet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $changedFields = [];

            foreach (['name', 'description'] as $field) {
                if (array_key_exists($field, $data)) {
                    $grantSet->{$field} = $data[$field];
                    $changedFields[] = $field;
                }
            }

            if ($changedFields !== []) {
                $grantSet->save();
            }

            if ($legacyAppIds !== null) {
                $existingIds = $grantSet->items()
                    ->pluck('legacy_app_id')
                    ->all();

                $removedIds = array_diff(
                    $existingIds,
                    $legacyAppIds,
                );

                $addedIds = array_diff(
                    $legacyAppIds,
                    $existingIds,
                );

                if ($removedIds !== []) {
                    $grantSet->items()
                        ->whereIn('legacy_app_id', $removedIds)
                        ->delete();
                }

                foreach ($addedIds as $legacyAppId) {
                    $grantSet->items()->create([
                        'legacy_app_id' => $legacyAppId,
                    ]);
                }

                $changedFields[] = 'legacy_app_ids';
            }

            if ($changedFields !== []) {
                Audit::log(
                    'access.legacy_app_grant_set_updated',
                    $actor->id,
                    null,
                    'legacy_app_grant_set',
                    $grantSet->id,
                    [
                        'changed_fields' => $changedFields,
                        'legacy_app_ids' => $grantSet->items()
                            ->pluck('legacy_app_id')
                            ->all(),
                    ],
                );
            }

            return $grantSet->fresh('items');
        });
    }

    /**
     * Archive a Grant Set without deleting its memberships.
     */
    public function archive(
        LegacyAppGrantSet $grantSet,
        User $actor,
    ): LegacyAppGrantSet {
        $this->authorizeActor($actor);

        return DB::transaction(function () use (
            $grantSet,
            $actor,
        ): LegacyAppGrantSet {
            $grantSet = LegacyAppGrantSet::query()
                ->whereKey($grantSet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $grantSet->delete();

            Audit::log(
                'access.legacy_app_grant_set_archived',
                $actor->id,
                null,
                'legacy_app_grant_set',
                $grantSet->id,
                ['name' => $grantSet->name],
            );

            return $grantSet->fresh('items');
        });
    }

    /**
     * Restore an archived Grant Set.
     */
    public function restore(
        string $grantSetId,
        User $actor,
    ): LegacyAppGrantSet {
        $this->authorizeActor($actor);

        return DB::transaction(function () use (
            $grantSetId,
            $actor,
        ): LegacyAppGrantSet {
            $grantSet = LegacyAppGrantSet::withTrashed()
                ->whereKey($grantSetId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $grantSet->trashed()) {
                throw ValidationException::withMessages([
                    'grant_set' => [
                        'This Grant Set is not archived.',
                    ],
                ]);
            }

            $grantSet->restore();

            Audit::log(
                'access.legacy_app_grant_set_restored',
                $actor->id,
                null,
                'legacy_app_grant_set',
                $grantSet->id,
                ['name' => $grantSet->name],
            );

            return $grantSet->fresh('items');
        });
    }

    /**
     * Apply a Grant Set additively to a user.
     *
     * Every successful application is recorded as an immutable event.
     * Existing active grants are preserved.
     * Revoked grants result in a new grant period.
     */
    public function apply(
        LegacyAppGrantSet $grantSet,
        User $target,
        User $actor,
    ): LegacyAppGrantSetApplicationEvent {
        $this->authorizeActor($actor);

        return DB::transaction(function () use (
            $grantSet,
            $target,
            $actor,
        ): LegacyAppGrantSetApplicationEvent {
            $grantSet = LegacyAppGrantSet::withTrashed()
                ->whereKey($grantSet->id)
                ->lockForUpdate()
                ->with('items')
                ->firstOrFail();

            if ($grantSet->trashed()) {
                throw ValidationException::withMessages([
                    'grant_set' => [
                        'An archived Grant Set cannot be applied.',
                    ],
                ]);
            }

            // Serialize Grant Set applications targeting the same user.
            User::query()
                ->whereKey($target->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Preserve the complete configured membership as the snapshot.
            $legacyAppIds = $grantSet->items
                ->pluck('legacy_app_id')
                ->map(fn ($id) => (string) $id)
                ->values()
                ->all();

            $now = now();

            $applicationEvent = LegacyAppGrantSetApplicationEvent::query()
                ->create([
                    'grant_set_id' => $grantSet->id,
                    'user_id' => $target->id,
                    'actor_id' => $actor->id,
                    'legacy_app_ids' => $legacyAppIds,
                    'occurred_at' => $now,
                ]);

            // An empty Grant Set still has a valid application history event.
            if ($legacyAppIds === []) {
                Audit::log(
                    'access.legacy_app_grant_set_applied',
                    $actor->id,
                    null,
                    entityType: 'legacy_app_grant_set',
                    entityId: $grantSet->id,
                    meta: [
                        'target_user_id' => $target->id,
                        'application_event_id' => $applicationEvent->id,
                        'legacy_app_ids' => [],
                        'granted_app_ids' => [],
                    ],
                );

                DB::afterCommit(
                    fn () => $this->access->forget($target->id)
                );

                return $applicationEvent;
            }

            // The application directory belongs to concord_platform.
            $activeAppIds = DB::connection('concord_platform')
                ->table('legacy_apps')
                ->whereIn('id', $legacyAppIds)
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();

            // Find currently active grants so they are not duplicated.
            $existingActiveIds = UserLegacyAppGrant::query()
                ->where('user_id', $target->id)
                ->whereIn('legacy_app_id', $activeAppIds)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->pluck('legacy_app_id')
                ->map(fn ($id) => (string) $id)
                ->all();

            $existingActiveIds = array_flip($existingActiveIds);
            $grantedAppIds = [];

            foreach ($activeAppIds as $legacyAppId) {
                if (isset($existingActiveIds[$legacyAppId])) {
                    continue;
                }

                // A revoked grant is never reactivated.
                // A new grant period is created instead.
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
                    'source' => LegacyAppGrantEvent::SOURCE_GRANT_SET,
                    'metadata' => [
                        'grant_set_id' => $grantSet->id,
                        'application_event_id' => $applicationEvent->id,
                    ],
                    'occurred_at' => $now,
                ]);

                $grantedAppIds[] = $legacyAppId;
            }

            Audit::log(
                'access.legacy_app_grant_set_applied',
                $actor->id,
                null,
                'legacy_app_grant_set',
                $grantSet->id,
                [
                    'target_user_id' => $target->id,
                    'application_event_id' => $applicationEvent->id,
                    'legacy_app_ids' => $legacyAppIds,
                    'granted_app_ids' => $grantedAppIds,
                ],
            );

            DB::afterCommit(
                fn () => $this->access->forget($target->id)
            );

            return $applicationEvent;
        });
    }

    /**
     * Validate application references against the platform directory.
     *
     * Inactive applications are valid Grant Set members.
     */
    private function validateLegacyAppIds(array $legacyAppIds): array
    {
        $legacyAppIds = array_values(array_unique($legacyAppIds));

        if ($legacyAppIds === []) {
            return [];
        }

        $existingIds = DB::connection('concord_platform')
            ->table('legacy_apps')
            ->whereIn('id', $legacyAppIds)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $missingIds = array_diff($legacyAppIds, $existingIds);

        if ($missingIds !== []) {
            throw ValidationException::withMessages([
                'legacy_app_ids' => [
                    'One or more selected applications do not exist.',
                ],
            ]);
        }

        return $legacyAppIds;
    }

    private function authorizeActor(User $actor): void
    {
        if (! $this->platformAdministrators->isDesignated($actor->id)) {
            throw new AuthorizationException(
                'Only the Concord Administrator can manage Grant Sets.'
            );
        }
    }
}
