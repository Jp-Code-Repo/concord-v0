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

}
