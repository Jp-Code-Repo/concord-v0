<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DemoIdentitySeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'Demo identity seeding is forbidden in production.'
            );
        }

        if (! config('concord.demo_seed_enabled', false)) {
            throw new RuntimeException(
                'Demo seeding is disabled. Set IAM_DEMO_SEED=true explicitly.'
            );
        }

        DB::transaction(function (): void {
            $admin = User::where('is_it_admin', true)
                ->where('status', 'active')
                ->first();

            if (! $admin) {
                throw new RuntimeException(
                    'An active IT administrator must exist before demo seeding.'
                );
            }

            $office = DB::table('offices')
                ->where('code', 'registrar-office')
                ->first();

            if (! $office) {
                throw new RuntimeException(
                    'Registrar office not found. Run the normal database seeder first.'
                );
            }

            $blocks = DB::table('blocks')
                ->whereIn('key', [
                    'registrar.student-profile.view',
                    'registrar.student-profile.basic-info',
                    'registrar.student-profile.chinese-name',
                    'registrar.student-profile.change-gender',
                    'registrar.change-section.view',
                    'registrar.change-section.change-section',
                ])
                ->pluck('id', 'key');

            $requiredBlocks = [
                'registrar.student-profile.view',
                'registrar.student-profile.basic-info',
                'registrar.change-section.view',
                'registrar.change-section.change-section',
            ];

            foreach ($requiredBlocks as $key) {
                if (! $blocks->has($key)) {
                    throw new RuntimeException(
                        "Required Registrar Block is missing: {$key}"
                    );
                }
            }

            $this->ensureOfficeApp($office->id);

            $maria = $this->ensureUser(
                'maria.reyes',
                'maria.reyes@concord.local',
                'Maria Reyes',
                'Demo-Maria-2026!'
            );

            $carlos = $this->ensureUser(
                'carlos.lim',
                'carlos.lim@concord.local',
                'Carlos Lim',
                'Demo-Carlos-2026!'
            );

            $ana = $this->ensureUser(
                'ana.santos',
                'ana.santos@concord.local',
                'Ana Santos',
                'Demo-Ana-2026!'
            );

            $elena = $this->ensureUser(
                'elena.cruz',
                'elena.cruz@concord.local',
                'Elena Cruz',
                'Demo-Elena-2026!'
            );

            $disabled = $this->ensureUser(
                'disabled.user',
                'disabled.user@concord.local',
                'Disabled Demo User',
                'Demo-Disabled-2026!'
            );

            $this->ensureActiveMembership($maria->id, $office->id);
            $this->ensureActiveMembership($carlos->id, $office->id);
            $this->ensureActiveMembership($elena->id, $office->id);

            $this->ensureGrant(
                $maria->id,
                $office->id,
                $blocks['registrar.student-profile.view'],
                $admin->id
            );

            $this->ensureGrant(
                $maria->id,
                $office->id,
                $blocks['registrar.student-profile.basic-info'],
                $admin->id
            );

            $this->ensureGrant(
                $carlos->id,
                $office->id,
                $blocks['registrar.change-section.view'],
                $admin->id
            );

            $this->ensureGrant(
                $carlos->id,
                $office->id,
                $blocks['registrar.change-section.change-section'],
                $admin->id
            );

            if (
                $office->head_user_id !== null
                && $office->head_user_id !== $elena->id
            ) {
                throw new RuntimeException(
                    'Registrar Office already has a different Office Head. '
                    . 'Resolve the existing assignment before seeding demo identities.'
                );
            }

            if ($office->head_user_id === null) {
                DB::table('offices')
                    ->where('id', $office->id)
                    ->update([
                        'head_user_id' => $elena->id,
                        'updated_at' => now(),
                    ]);
            }

            $headBlocks = [
                $blocks['registrar.student-profile.view'] => false,
                $blocks['registrar.student-profile.basic-info'] => true,
                $blocks['registrar.change-section.view'] => false,
            ];

            foreach ($headBlocks as $blockId => $canDelegate) {
                DB::table('office_head_blocks')->insertOrIgnore([
                    'office_id' => $office->id,
                    'block_id' => $blockId,
                    'can_delegate' => $canDelegate,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $disabled->status = 'disabled';
            $disabled->disabled_at ??= now();
            $disabled->save();

            // Revoke any existing sessions for this disabled demo account.
            DB::table('auth_sessions')
                ->where('user_id', $disabled->id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'revoked_reason' => 'account_disabled',
                ]);

            $this->command?->info(
                'Demo identities seeded. Existing account passwords were preserved.'
            );
        });
    }

    private function ensureUser(
        string $username,
        string $email,
        string $displayName,
        string $password,
    ): User {
        $user = User::firstOrCreate(
            ['username' => $username],
            [
                'email' => $email,
                'display_name' => $displayName,
                'password' => Hash::make($password),
                'status' => 'active',
                'is_it_admin' => false,
            ],
        );

        if ($user->is_it_admin) {
            throw new RuntimeException(
                "Demo username '{$username}' belongs to an IT administrator. "
                . 'Refusing to modify or reuse this account.'
            );
        }

        return $user;
    }

    private function ensureOfficeApp(string $officeId): void
    {
        $appId = DB::table('apps')
            ->where('key', 'registrar')
            ->value('id');

        if (! $appId) {
            throw new RuntimeException('Registrar application not found.');
        }

        DB::table('office_apps')->updateOrInsert([
            'office_id' => $officeId,
            'app_id' => $appId,
        ]);
    }

    private function ensureActiveMembership(
        string $userId,
        string $officeId,
    ): string {
        $membership = DB::table('memberships')
            ->where('user_id', $userId)
            ->where('office_id', $officeId)
            ->whereNull('ended_at')
            ->first();

        if ($membership) {
            return $membership->id;
        }

        $id = (string) Str::ulid();

        DB::table('memberships')->insert([
            'id' => $id,
            'user_id' => $userId,
            'office_id' => $officeId,
            'started_at' => now(),
            'ended_at' => null,
            'ended_reason' => null,
        ]);

        return $id;
    }

    private function ensureGrant(
        string $userId,
        string $officeId,
        string $blockId,
        string $adminId,
    ): void {
        $membershipId = DB::table('memberships')
            ->where('user_id', $userId)
            ->where('office_id', $officeId)
            ->whereNull('ended_at')
            ->value('id');

        if (! $membershipId) {
            throw new RuntimeException(
                "Active membership missing for demo user {$userId}."
            );
        }

        $exists = DB::table('grants')
            ->where('membership_id', $membershipId)
            ->where('block_id', $blockId)
            ->whereNull('revoked_at')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('grants')->insert([
            'id' => (string) Str::ulid(),
            'membership_id' => $membershipId,
            'block_id' => $blockId,
            'granted_by' => $adminId,
            'granted_at' => now(),
            'revoked_by' => null,
            'revoked_at' => null,
            'revoke_reason' => null,
        ]);
    }
}
