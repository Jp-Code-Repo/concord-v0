<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * What a person can use = IT admin (everything) OR, per office they belong to:
 *   - head of the office: every block of the office's apps
 *   - otherwise: the blocks with an active (not revoked) grant on their membership
 *
 * Cached per user; call forget() whenever a grant, membership or head changes.
 */
class AccessService
{
    private const TTL = 300;

    public function forget(string $userId): void
    {
        Cache::forget("access:{$userId}");
        Cache::forget("access:{$userId}:it-admin");
    }

    public function forUser(string $userId, bool $isItAdmin): array
    {
        $cacheKey = $isItAdmin
            ? "access:{$userId}:it-admin"
            : "access:{$userId}";

        return Cache::remember(
            $cacheKey,
            self::TTL,
            fn () => $this->compute($userId, $isItAdmin),
        );
    }

    private function compute(string $userId, bool $isItAdmin): array
    {
        if ($isItAdmin) {
            return [
                'all_access' => true,
                'apps' => $this->apps(DB::table('apps')->pluck('id')->all()),
                'offices' => [],
            ];
        }

        $offices = [];
        $seen = [];

        // Offices this user heads.
        foreach (DB::table('offices')->where('head_user_id', $userId)->get(['id', 'name']) as $o) {
            $appIds = $this->officeAppIds($o->id);
            $offices[] = [
                'office_id' => $o->id,
                'name' => $o->name,
                'is_head' => true,
                'apps' => $this->apps($appIds),
                'blocks' => DB::table('office_head_blocks')
                    ->join('blocks', 'blocks.id', '=', 'office_head_blocks.block_id')
                    ->join('app_groups', 'app_groups.id', '=', 'blocks.group_id')
                    ->where('office_head_blocks.office_id', $o->id)
                    ->whereIn('app_groups.app_id', $appIds)
                    ->distinct()
                    ->orderBy('blocks.key')
                    ->pluck('blocks.key')
                    ->all(),
            ];
            $seen[] = $o->id;
        }

        // Offices where this user is staff.
        $memberships = DB::table('memberships')
            ->join('offices', 'offices.id', '=', 'memberships.office_id')
            ->where('memberships.user_id', $userId)
            ->whereNull('memberships.ended_at')
            ->whereNotIn('offices.id', $seen)
            ->get(['memberships.id as membership_id', 'offices.id as office_id', 'offices.name']);

        foreach ($memberships as $m) {
            $offices[] = [
                'office_id' => $m->office_id,
                'name' => $m->name,
                'is_head' => false,
                'apps' => $this->apps($this->officeAppIds($m->office_id)),
                'blocks' => DB::table('grants')
                    ->join('blocks', 'blocks.id', '=', 'grants.block_id')
                    ->where('grants.membership_id', $m->membership_id)
                    ->whereNull('grants.revoked_at')
                    ->distinct()->pluck('blocks.key')->all(),
            ];
        }

        return ['all_access' => false, 'apps' => [], 'offices' => $offices];
    }

    private function officeAppIds(string $officeId): array
    {
        return DB::table('office_apps')->where('office_id', $officeId)->pluck('app_id')->all();
    }

    private function apps(array $ids): array
    {
        return DB::table('apps')->whereIn('id', $ids)->orderBy('sort_order')
            ->get(['key', 'name'])
            ->map(fn ($a) => ['key' => $a->key, 'name' => $a->name])
            ->all();
    }
}
