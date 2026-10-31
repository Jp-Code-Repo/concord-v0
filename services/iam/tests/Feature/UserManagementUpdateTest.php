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
use Illuminate\Routing\Middleware\ThrottleRequests;

class UserManagementUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_administrator_can_update_personal_information(): void
    {
        [$admin, $cookie] = $this->createAdministrator();

        $user = $this->createTargetUser();

        $response = $this->patchUser($user->id, [
            'first_name' => 'Juan Carlos',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'name_suffix' => 'Jr.',
            'gender' => 'Male',
            'email' => 'juan@example.com',
        ], $cookie);

        $response->assertOk();

        $this->assertDatabaseHas('people', [
            'id' => $user->person_id,
            'first_name' => 'Juan Carlos',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'name_suffix' => 'Jr.',
            'gender' => 'Male',
            'email' => 'juan@example.com',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'identity.user_updated',
            'entity_type' => 'user',
            'entity_id' => $user->id,
        ]);
    }

    public function test_administrator_can_update_username(): void
    {
        [, $cookie] = $this->createAdministrator();

        $user = $this->createTargetUser();

        $response = $this->patchUser($user->id, [
            'username' => 'juan.new',
        ], $cookie);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => 'juan.new',
        ]);
    }

    public function test_duplicate_username_is_rejected(): void
    {
        [, $cookie] = $this->createAdministrator();

        $target = $this->createTargetUser();
        $existing = $this->createTargetUser('existing.user');

        $response = $this->patchUser($target->id, [
            'username' => $existing->username,
        ], $cookie);

        $response->assertUnprocessable();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'username' => $target->username,
        ]);
    }

    public function test_administrator_can_change_person_types_without_deleting_student_identity(): void
    {
        [, $cookie] = $this->createAdministrator();

        $user = $this->createTargetUser();

        PersonType::query()->create([
            'person_id' => $user->person_id,
            'type' => PersonType::STUDENT,
        ]);

        Student::query()->create([
            'person_id' => $user->person_id,
            'student_number' => 'STU-001',
        ]);

        $response = $this->patchUser($user->id, [
            'types' => [PersonType::EMPLOYEE],
        ], $cookie);

        $response->assertOk();

        $this->assertDatabaseHas('person_types', [
            'person_id' => $user->person_id,
            'type' => PersonType::EMPLOYEE,
        ]);

        $this->assertDatabaseMissing('person_types', [
            'person_id' => $user->person_id,
            'type' => PersonType::STUDENT,
        ]);

        $this->assertDatabaseHas('students', [
            'person_id' => $user->person_id,
            'student_number' => 'STU-001',
        ]);
    }

    public function test_administrator_can_update_student_number(): void
    {
        [, $cookie] = $this->createAdministrator();

        $user = $this->createTargetUser();

        PersonType::query()->create([
            'person_id' => $user->person_id,
            'type' => PersonType::STUDENT,
        ]);

        Student::query()->create([
            'person_id' => $user->person_id,
            'student_number' => 'STU-001',
        ]);

        $response = $this->patchUser($user->id, [
            'student_number' => 'STU-002',
        ], $cookie);

        $response->assertOk();

        $this->assertDatabaseHas('students', [
            'person_id' => $user->person_id,
            'student_number' => 'STU-002',
        ]);
    }

    private function createAdministrator(): array
    {
        $admin = $this->createTargetUser('administrator');

        $designation = new PlatformAdministrator();
        $designation->id = 1;
        $designation->user_id = $admin->id;
        $designation->save();

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

        return [$admin, $cookie->getValue()];
    }

    private function createTargetUser(string $username = 'juan.delacruz'): User
    {
        $person = Person::query()->create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
        ]);

        return User::query()->create([
            'person_id' => $person->id,
            'username' => $username,
            'password' => 'test-password',
            'status' => User::STATUS_ACTIVE,
            'is_it_admin' => false,
        ]);
    }

    private function patchUser(
        string $userId,
        array $data,
        string $cookie
    ) {
        return $this->call(
            'PATCH',
            "/api/iam/admin/users/{$userId}",
            $data,
            [SessionService::COOKIE => $cookie],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );
    }
}