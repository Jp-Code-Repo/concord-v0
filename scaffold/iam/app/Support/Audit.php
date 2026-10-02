<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Audit
{
    /**
     * Append-only record of who did what. $officeId is the office the actor was
     * acting under (null for account-level actions such as login).
     */
    public static function log(
        string $action,
        ?string $userId = null,
        ?Request $request = null,
        ?string $officeId = null,
        ?string $entityType = null,
        ?string $entityId = null,
        array $meta = [],
    ): void {
        DB::table('audit_logs')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $userId,
            'office_id' => $officeId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'meta' => $meta ? json_encode($meta) : null,
            'ip_address' => $request?->ip(),
            'created_at' => now(),
        ]);
    }
}
