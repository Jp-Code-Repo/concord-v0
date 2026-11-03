<?php

namespace App\Services;

use App\Models\LegacyAppGrantSet;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LegacyAppGrantSetService
{
    public function __construct(
        private readonly PlatformAdministratorService $platformAdministrators,
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