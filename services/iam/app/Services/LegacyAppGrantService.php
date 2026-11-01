<?php

namespace App\Services;

use App\Models\LegacyAppGrantEvent;
use App\Models\User;
use App\Models\UserLegacyAppGrant;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LegacyAppGrantService
{
    public function __construct(
        private readonly PlatformAdministratorService $platformAdministrators,
    ) {}

    /**
     * Grant an active legacy application to a user.
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

    private function authorizeActor(User $actor): void
    {
        if (! $this->platformAdministrators->isDesignated($actor->id)) {
            throw new AuthorizationException(
                'Only the Concord Administrator can manage legacy application grants.'
            );
        }
    }
}
