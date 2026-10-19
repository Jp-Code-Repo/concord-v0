<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IamAppCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(bool $isItAdmin, string $username): User
    {
        return User::create([
            'username' => $username,
            'email' => $username.'@example.test',
            'display_name' => ucfirst($username),
            'password' => 'correct-horse-battery',
            'status' => 'active',
            'is_it_admin' => $isItAdmin,
        ]);
    }

    private function loginAs(User $user): string
    {
        $response = $this->postJson('/api/iam/login', [
            'username' => $user->username,
            'password' => 'correct-horse-battery',
        ])->assertOk()->assertCookie(SessionService::COOKIE);

        return $response->getCookie(SessionService::COOKIE, false)->getValue();
    }

    public function test_non_it_admin_cannot_access_application_catalog_administration(): void
    {
        $user = $this->makeUser(false, 'staff');
        $token = $this->loginAs($user);

        $this->withCredentials()->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->getJson('/api/iam/admin/apps')
            ->assertForbidden();

        $this->withCredentials()->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson('/api/iam/admin/apps', ['key' => 'hris', 'name' => 'HRIS'])
            ->assertForbidden();
    }

    public function test_it_admin_can_create_and_update_an_application_and_changes_are_audited(): void
    {
        $admin = $this->makeUser(true, 'itadmin');
        $token = $this->loginAs($admin);

        $created = $this->withCredentials()->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson('/api/iam/admin/apps', [
                'key' => 'hris',
                'name' => 'Human Resources',
                'sort_order' => 2,
            ])
            ->assertCreated()
            ->assertJsonPath('data.key', 'hris')
            ->assertJsonPath('data.name', 'Human Resources')
            ->assertJsonPath('data.sort_order', 2);

        $appId = $created->json('data.id');

        $this->withCredentials()->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->patchJson('/api/iam/admin/apps/'.$appId, ['name' => 'HRIS', 'sort_order' => 1])
            ->assertOk()
            ->assertJsonPath('data.name', 'HRIS')
            ->assertJsonPath('data.sort_order', 1);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'iam.app.created',
            'entity_type' => 'app',
            'entity_id' => $appId,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'iam.app.updated',
            'entity_type' => 'app',
            'entity_id' => $appId,
        ]);
    }

    public function test_application_key_must_be_unique_and_use_a_stable_slug_format(): void
    {
        $admin = $this->makeUser(true, 'itadmin');
        $token = $this->loginAs($admin);
        $appId = (string) Str::ulid();

        \DB::table('apps')->insert([
            'id' => $appId,
            'key' => 'hris',
            'name' => 'HRIS',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withCredentials()->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson('/api/iam/admin/apps', ['key' => 'hris', 'name' => 'Duplicate'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key');

        $this->withCredentials()->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson('/api/iam/admin/apps', ['key' => 'HRIS app', 'name' => 'Invalid key'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key');
    }

    public function test_catalog_index_returns_applications_with_groups_and_blocks(): void
    {
        $admin = $this->makeUser(true, 'itadmin');
        $token = $this->loginAs($admin);
        $appId = (string) Str::ulid();
        $groupId = (string) Str::ulid();
        $blockId = (string) Str::ulid();
        $now = now();

        \DB::table('apps')->insert([
            'id' => $appId, 'key' => 'hris', 'name' => 'HRIS', 'sort_order' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        \DB::table('app_groups')->insert([
            'id' => $groupId, 'app_id' => $appId, 'key' => 'employee-profile',
            'name' => 'Employee Profile', 'sort_order' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        \DB::table('blocks')->insert([
            'id' => $blockId, 'group_id' => $groupId,
            'key' => 'hris.employee-profile.view', 'name' => 'View',
            'description' => null, 'is_view' => true, 'sort_order' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->withCredentials()->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->getJson('/api/iam/admin/apps')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'hris')
            ->assertJsonPath('data.0.groups.0.key', 'employee-profile')
            ->assertJsonPath('data.0.groups.0.blocks.0.key', 'hris.employee-profile.view')
            ->assertJsonPath('data.0.groups.0.blocks.0.is_view', true);
    }

    public function test_removed_it_admin_privilege_is_enforced_without_relogging_in(): void
    {
        $admin = $this->makeUser(true, 'itadmin');
        $token = $this->loginAs($admin);

        \DB::table('users')->where('id', $admin->id)->update(['is_it_admin' => false]);

        $this->withCredentials()->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->getJson('/api/iam/admin/apps')
            ->assertForbidden();
    }
}
