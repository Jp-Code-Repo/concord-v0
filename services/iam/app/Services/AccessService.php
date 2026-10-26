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
     * This is a compatibility response only. The final
     * roles and permissions architecture remains undecided.
     */
    public function forUser(string $userId, bool $isItAdmin): array
    {
        $cacheKey = $isItAdmin
            ? "access:{$userId}:it-admin"
            : "access:{$userId}";

        return Cache::remember(
            $cacheKey,
            self::TTL,
            fn (): array => [
                'all_access' => $isItAdmin,
                'legacy_apps' => DB::connection('concord_platform')
                    ->table('legacy_apps')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name', 'url', 'icon', 'description'])
                    ->toArray(),
                'apps' => [],
                'offices' => [],
            ],
        );
    }
}