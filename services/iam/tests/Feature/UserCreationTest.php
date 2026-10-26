<?php

namespace Tests\Feature;

use App\Models\Person;
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
        $person = Person::create([
            'first_name' => 'Concord',
            'last_name' => 'Administrator',
            'email' => 'admin@example.test',
            'preferences' => [],
        ]);

        return User::create([
            'person_id' => $person->id,
            'username' => 'concord-admin',
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
                'first_name' => 'New',
                'middle_name' => null,
                'last_name' => 'Employee',
                'email' => 'employee@example.test',
                'types' => ['employee'],
            ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Account created.')
            ->assertJsonPath('user.username', 'new.employee')
            ->assertJsonPath('person.email', 'employee@example.test')
            ->assertJsonPath('person.first_name', 'New')
            ->assertJsonPath('person.types.0', 'employee')
            ->assertJsonPath('user.status', 'active')
            ->assertJsonStructure([
                'person' => ['id', 'first_name', 'last_name', 'email', 'preferences', 'types'],
                'user' => ['id', 'person_id', 'username', 'status'],
                'initial_password',
            ]);

        $userId = $response->json('user.id');
        $initialPassword = $response->json('initial_password');

        $this->assertNotEmpty($userId);
        $this->assertNotEmpty($initialPassword);

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'username' => 'new.employee',
            'person_id' => $response->json('person.id'),
            'is_it_admin' => false,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('people', [
            'id' => $response->json('person.id'),
            'first_name' => 'New',
            'last_name' => 'Employee',
            'email' => 'employee@example.test',
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
