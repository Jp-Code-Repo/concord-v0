<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserCreationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::create([
            'username' => 'concord-admin',
            'email' => 'admin@example.test',
            'display_name' => 'Concord Administrator',
            'password' => 'correct-horse-battery',
            'status' => 'active',
            'is_it_admin' => true,
        ]);
    }

    private function loginAs(User $user): string
    {
        $response = $this->postJson('/api/iam/login', [
            'username' => $user->username,
            'password' => 'correct-horse-battery',
        ])->assertOk();

        return $response->getCookie(
            SessionService::COOKIE,
            false
        )->getValue();
    }

    public function test_it_admin_can_create_a_regular_user(): void
    {
        $admin = $this->makeAdmin();
        $token = $this->loginAs($admin);

        $response = $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson('/api/iam/admin/users', [
                'username' => 'new.employee',
                'email' => 'employee@example.test',
                'display_name' => 'New Employee',
            ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Account created.')
            ->assertJsonPath('user.username', 'new.employee')
            ->assertJsonPath('user.email', 'employee@example.test')
            ->assertJsonPath('user.status', 'active')
            ->assertJsonStructure([
                'user' => ['id', 'username', 'email', 'display_name', 'status'],
                'initial_password',
            ]);

        $userId = $response->json('user.id');
        $initialPassword = $response->json('initial_password');

        $this->assertNotEmpty($userId);
        $this->assertNotEmpty($initialPassword);

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'username' => 'new.employee',
            'email' => 'employee@example.test',
            'is_it_admin' => false,
            'status' => 'active',
        ]);

        $createdUser = User::findOrFail($userId);

        $this->assertTrue(Hash::check(
            $initialPassword,
            $createdUser->password
        ));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'identity.user_created',
            'entity_type' => 'user',
            'entity_id' => $userId,
        ]);
    }
}
