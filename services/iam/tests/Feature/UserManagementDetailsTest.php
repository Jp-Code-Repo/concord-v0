<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonType;
use App\Models\PlatformAdministrator;
use App\Models\Student;
use App\Models\User;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class UserManagementDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_view_user_details(): void
    {
        $admin = $this->createUser('administrator');
        $this->designateAdministrator($admin);

        $person = Person::query()->create([
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'name_suffix' => 'Jr.',
            'gender' => 'Male',
            'email' => 'juan@example.com',
        ]);

        PersonType::query()->create([
            'person_id' => $person->id,
            'type' => PersonType::STUDENT,
        ]);

        PersonType::query()->create([
            'person_id' => $person->id,
            'type' => PersonType::EMPLOYEE,
        ]);

        Student::query()->create([
            'person_id' => $person->id,
            'student_number' => '2026-00125',
        ]);

        $user = User::query()->create([
            'person_id' => $person->id,
            'username' => 'juan.delacruz',
            'password' => 'test-password',
            'status' => User::STATUS_ACTIVE,
            'is_it_admin' => false,
        ]);

        $cookie = $this->administratorCookie($admin);

        $response = $this->getUserDetails($user->id, $cookie);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.person_id', $person->id)
            ->assertJsonPath('user.username', 'juan.delacruz')
            ->assertJsonPath('user.status', User::STATUS_ACTIVE)
            ->assertJsonPath('person.id', $person->id)
            ->assertJsonPath('person.first_name', 'Juan')
            ->assertJsonPath('person.middle_name', 'Santos')
            ->assertJsonPath('person.last_name', 'Dela Cruz')
            ->assertJsonPath('person.name_suffix', 'Jr.')
            ->assertJsonPath('person.gender', 'Male')
            ->assertJsonPath('person.email', 'juan@example.com')
            ->assertJsonPath('person.student_number', '2026-00125');

        $this->assertEqualsCanonicalizing(
            [PersonType::STUDENT, PersonType::EMPLOYEE],
            $response->json('person.types')
        );
    }

    public function test_administrator_receives_not_found_for_unknown_user(): void
    {
        $admin = $this->createUser('administrator');
        $this->designateAdministrator($admin);

        $cookie = $this->administratorCookie($admin);

        $response = $this->getUserDetails(
            '01J00000000000000000000000',
            $cookie
        );

        $response->assertNotFound();
    }

    private function createUser(string $username): User
    {
        $person = Person::query()->create([
            'first_name' => ucfirst($username),
            'last_name' => 'Test',
        ]);

        return User::query()->create([
            'person_id' => $person->id,
            'username' => $username,
            'password' => 'test-password',
            'status' => User::STATUS_ACTIVE,
            'is_it_admin' => false,
        ]);
    }

    private function designateAdministrator(User $user): void
    {
        $designation = new PlatformAdministrator();
        $designation->id = 1;
        $designation->user_id = $user->id;
        $designation->save();
    }

    private function administratorCookie(User $admin): string
    {
        $response = $this->postJson('/api/iam/login', [
            'username' => $admin->username,
            'password' => 'test-password',
        ]);

        $response->assertOk();

        $cookie = collect($response->headers->getCookies())
            ->first(
                fn (Cookie $cookie) =>
                    $cookie->getName() === SessionService::COOKIE
            );

        $this->assertNotNull($cookie);

        return $cookie->getValue();
    }

    private function getUserDetails(string $userId, string $cookie)
    {
        return $this->call(
            'GET',
            "/api/iam/admin/users/{$userId}",
            [],
            [SessionService::COOKIE => $cookie],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );
    }
}