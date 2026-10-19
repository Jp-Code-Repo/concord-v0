<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccessService;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function makeUser(
        string $username,
        bool $isItAdmin = false,
        string $status = 'active',
    ): User {
        return User::create([
            'username' => $username,
            'email' => "{$username}@example.test",
            'display_name' => $username,
            'password' => 'correct-horse-battery',
            'status' => $status,
            'is_it_admin' => $isItAdmin,
        ]);
    }

    private function loginAs(User $user): string
    {
        $response = $this->postJson('/api/iam/login', [
            'username' => $user->username,
            'password' => 'correct-horse-battery',
        ])->assertOk()
          ->assertCookie(SessionService::COOKIE);

        return $response
            ->getCookie(SessionService::COOKIE, false)
            ->getValue();
    }

    public function test_it_admin_can_disable_an_account_and_revoke_its_sessions(): void
    {
        $admin = $this->makeUser('it-admin', true);
        $target = $this->makeUser('target');

        $targetToken = $this->loginAs($target);

        $this->assertNotNull(
            app(SessionService::class)->resolve($targetToken)
        );

        // Populate both effective-access cache keys.
        Cache::put("access:{$target->id}", ['cached' => true], 300);
        Cache::put("access:{$target->id}:it-admin", ['cached' => true], 300);

        $adminToken = $this->loginAs($admin);

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $adminToken)
            ->postJson("/api/iam/users/{$target->id}/disable")
            ->assertOk()
            ->assertJsonPath('message', 'Account disabled.');

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'status' => 'disabled',
        ]);

        $this->assertNotNull(
            $target->fresh()->disabled_at
        );

        $this->assertDatabaseHas('auth_sessions', [
            'user_id' => $target->id,
            'token_hash' => hash('sha256', $targetToken),
            'revoked_reason' => 'account_disabled',
        ]);

        $this->assertNull(
            app(SessionService::class)->resolve($targetToken)
        );

        $this->assertFalse(Cache::has("access:{$target->id}"));
        $this->assertFalse(Cache::has("access:{$target->id}:it-admin"));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'identity.user_disabled',
            'entity_type' => 'user',
            'entity_id' => $target->id,
        ]);

        // The disabled user's existing token must no longer authorize requests.
        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $targetToken)
            ->getJson('/api/iam/me')
            ->assertUnauthorized();
    }

    public function test_non_it_admin_cannot_disable_an_account(): void
    {
        $staff = $this->makeUser('staff');
        $target = $this->makeUser('target');

        $token = $this->loginAs($staff);

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson("/api/iam/users/{$target->id}/disable")
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'user_id' => $staff->id,
            'action' => 'identity.user_disabled',
            'entity_id' => $target->id,
        ]);
    }

    public function test_already_disabled_account_cannot_be_disabled_again(): void
    {
        $admin = $this->makeUser('it-admin', true);
        $target = $this->makeUser('target');

        $token = $this->loginAs($admin);

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson("/api/iam/users/{$target->id}/disable")
            ->assertOk();

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson("/api/iam/users/{$target->id}/disable")
            ->assertStatus(409);
    }
}