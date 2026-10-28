<?php

namespace Tests\Feature;

use App\Models\Person;
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
        $person = Person::create([
            'first_name' => ucfirst($username),
            'last_name' => 'Test',
            'email' => "{$username}@example.test",
            'preferences' => [],
        ]);

        return User::create([
            'person_id' => $person->id,
            'username' => $username,
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

}