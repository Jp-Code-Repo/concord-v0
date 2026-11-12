<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AccessService
{
    private const TTL = 300;

    /**
     * Clear cached access data for a user.
     */
    public function forget(string $userId): void
    {
        Cache::forget("access:{$userId}");
        Cache::forget("access:{$userId}:it-admin");
    }

    /**
     * Return the current access response for a user.
     *
     * Legacy application access is determined exclusively by
     * the user's active individual grant records.
     *
     * The legacy IT administrator flag remains available through
     * the compatibility all_access field. It does not bypass
     * individual Legacy App grants.
     */
    public function forUser(string $userId, bool $isItAdmin): array
    {
        $cacheKey = $isItAdmin
            ? "access:{$userId}:it-admin"
            : "access:{$userId}";

        return Cache::remember(
            $cacheKey,
            self::TTL,
            function () use ($userId, $isItAdmin): array {
                $grantedAppIds = DB::table('user_legacy_app_grants')
                    ->where('user_id', $userId)
                    ->whereNull('revoked_at')
                    ->pluck('legacy_app_id');

                $legacyApps = DB::connection('concord_platform')
                    ->table('legacy_apps')
                    ->whereIn('id', $grantedAppIds)
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
                    ->toArray();

                return [
                    'all_access' => $isItAdmin,
                    'legacy_apps' => $legacyApps,
                    'apps' => [],
                    'offices' => [],
                ];
            },
        );
    }
}
