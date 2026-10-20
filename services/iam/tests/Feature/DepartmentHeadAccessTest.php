<?php

use App\Models\User;
use App\Services\AccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DepartmentHeadAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $username): User
    {
        return User::create([
            'username' => $username,
            'email' => "{$username}@example.test",
            'display_name' => $username,
            'password' => 'test-password',
            'status' => 'active',
        ]);
    }

    private function makeOffice(
        string $code,
        string $name,
        ?string $headUserId = null
    ): string {
        $id = (string) Str::ulid();

        DB::table('offices')->insert([
            'id' => $id,
            'code' => $code,
            'name' => $name,
            'head_user_id' => $headUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function makeApp(string $key, string $name): string
    {
        $id = (string) Str::ulid();

        DB::table('apps')->insert([
            'id' => $id,
            'key' => $key,
            'name' => $name,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function makeBlock(
        string $appId,
        string $groupKey,
        string $blockKey
    ): string {
        $groupId = (string) Str::ulid();

        DB::table('app_groups')->insert([
            'id' => $groupId,
            'app_id' => $appId,
            'key' => $groupKey,
            'name' => $groupKey,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $blockId = (string) Str::ulid();

        DB::table('blocks')->insert([
            'id' => $blockId,
            'group_id' => $groupId,
            'key' => $blockKey,
            'name' => $blockKey,
            'description' => null,
            'is_view' => false,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $blockId;
    }

    public function test_head_receives_only_blocks_configured_for_their_office(): void
    {
        $head = $this->makeUser('head');
        $appId = $this->makeApp('registrar', 'Registrar');
        $officeId = $this->makeOffice('REG', 'Registrar Office', $head->id);

        DB::table('office_apps')->insert([
            'office_id' => $officeId,
            'app_id' => $appId,
        ]);

        $configuredBlockId = $this->makeBlock(
            $appId,
            'students',
            'registrar.students.view'
        );

        $unconfiguredBlockId = $this->makeBlock(
            $appId,
            'student-maintenance',
            'registrar.students.delete'
        );

        DB::table('office_head_blocks')->insert([
            'office_id' => $officeId,
            'block_id' => $configuredBlockId,
            'can_delegate' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forget("access:{$head->id}");

        $access = app(AccessService::class)->forUser($head->id, false);

        $this->assertSame(
            ['registrar.students.view'],
            $access['offices'][0]['blocks']
        );

        $this->assertNotContains(
            'registrar.students.delete',
            $access['offices'][0]['blocks']
        );
    }

    public function test_head_configuration_does_not_grant_blocks_in_another_office(): void
    {
        $head = $this->makeUser('head');
        $staff = $this->makeUser('staff');
        $appId = $this->makeApp('registrar', 'Registrar');

        $registrarOfficeId = $this->makeOffice(
            'REG',
            'Registrar Office',
            $head->id
        );

        $itOfficeId = $this->makeOffice('IT', 'IT Office');

        DB::table('office_apps')->insert([
            [
                'office_id' => $registrarOfficeId,
                'app_id' => $appId,
            ],
            [
                'office_id' => $itOfficeId,
                'app_id' => $appId,
            ],
        ]);

        $registrarBlockId = $this->makeBlock(
            $appId,
            'registrar-students',
            'registrar.students.view'
        );

        $itBlockId = $this->makeBlock(
            $appId,
            'it-students',
            'registrar.students.edit'
        );

        DB::table('office_head_blocks')->insert([
            'office_id' => $registrarOfficeId,
            'block_id' => $registrarBlockId,
            'can_delegate' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $membershipId = (string) Str::ulid();

        DB::table('memberships')->insert([
            'id' => $membershipId,
            'user_id' => $head->id,
            'office_id' => $itOfficeId,
            'started_at' => now(),
            'ended_at' => null,
            'ended_reason' => null,
        ]);

        Cache::forget("access:{$head->id}");

        $access = app(AccessService::class)->forUser($head->id, false);

        $itOfficeAccess = collect($access['offices'])
            ->firstWhere('office_id', $itOfficeId);

        $this->assertNotNull($itOfficeAccess);
        $this->assertSame([], $itOfficeAccess['blocks']);
        $this->assertNotContains($itBlockId, $itOfficeAccess['blocks']);
    }

    public function test_staff_access_continues_to_depend_on_active_grants(): void
    {
        $staff = $this->makeUser('staff');
        $grantor = $this->makeUser('grantor');

        $appId = $this->makeApp('registrar', 'Registrar');
        $officeId = $this->makeOffice('REG', 'Registrar Office');

        DB::table('office_apps')->insert([
            'office_id' => $officeId,
            'app_id' => $appId,
        ]);

        $membershipId = (string) Str::ulid();

        DB::table('memberships')->insert([
            'id' => $membershipId,
            'user_id' => $staff->id,
            'office_id' => $officeId,
            'started_at' => now(),
            'ended_at' => null,
            'ended_reason' => null,
        ]);

        $activeBlockId = $this->makeBlock(
            $appId,
            'students',
            'registrar.students.view'
        );

        $revokedBlockId = $this->makeBlock(
            $appId,
            'student-maintenance',
            'registrar.students.edit'
        );

        DB::table('grants')->insert([
            [
                'id' => (string) Str::ulid(),
                'membership_id' => $membershipId,
                'block_id' => $activeBlockId,
                'granted_by' => $grantor->id,
                'granted_at' => now(),
                'revoked_by' => null,
                'revoked_at' => null,
                'revoke_reason' => null,
            ],
            [
                'id' => (string) Str::ulid(),
                'membership_id' => $membershipId,
                'block_id' => $revokedBlockId,
                'granted_by' => $grantor->id,
                'granted_at' => now(),
                'revoked_by' => $grantor->id,
                'revoked_at' => now(),
                'revoke_reason' => 'head',
            ],
        ]);

        Cache::forget("access:{$staff->id}");

        $access = app(AccessService::class)->forUser($staff->id, false);

        $this->assertSame(
            ['registrar.students.view'],
            $access['offices'][0]['blocks']
        );
    }


    public function test_it_admin_access_does_not_reuse_regular_user_cache(): void
    {
        $user = $this->makeUser('cache_test_user');
        $access = app(AccessService::class);

        Cache::forget("access:{$user->id}");
        Cache::forget("access:{$user->id}:it-admin");

        $regularAccess = $access->forUser($user->id, false);

        $this->assertFalse($regularAccess['all_access']);

        $adminAccess = $access->forUser($user->id, true);

        $this->assertTrue($adminAccess['all_access']);
    }

}
