<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonType;
use App\Models\Student;
use App\Models\User;
use App\Services\UserCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserCreationValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_username_is_rejected(): void
    {
        $this->createExistingUser('existing.user');

        try {
            app(UserCreationService::class)->create([
                'username' => 'existing.user',
                'first_name' => 'Another',
                'last_name' => 'Person',
                'types' => [PersonType::EMPLOYEE],
            ]);

            $this->fail('Expected duplicate username validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('username', $exception->errors());
        }

        $this->assertDatabaseCount('people', 1);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_student_type_requires_student_number(): void
    {
        try {
            app(UserCreationService::class)->create([
                'username' => 'new.student',
                'first_name' => 'New',
                'last_name' => 'Student',
                'types' => [PersonType::STUDENT],
            ]);

            $this->fail('Expected student number validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('student_number', $exception->errors());
        }

        $this->assertDatabaseCount('people', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_duplicate_student_number_is_rejected(): void
    {
        $existingPerson = Person::query()->create([
            'first_name' => 'Existing',
            'last_name' => 'Student',
        ]);

        $existingPerson->student()->create([
            'student_number' => 'STU-001',
        ]);

        try {
            app(UserCreationService::class)->create([
                'username' => 'another.student',
                'first_name' => 'Another',
                'last_name' => 'Student',
                'types' => [PersonType::STUDENT],
                'student_number' => 'STU-001',
            ]);

            $this->fail('Expected duplicate student number validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('student_number', $exception->errors());
        }

        $this->assertDatabaseCount('people', 1);
        $this->assertDatabaseCount('students', 1);
    }

    public function test_invalid_person_type_is_rejected(): void
    {
        try {
            app(UserCreationService::class)->create([
                'username' => 'invalid.type',
                'first_name' => 'Invalid',
                'last_name' => 'Type',
                'types' => ['unknown_type'],
            ]);

            $this->fail('Expected invalid person type validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('types', $exception->errors());
        }

        $this->assertDatabaseCount('people', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_valid_student_account_creates_student_record(): void
    {
        $result = app(UserCreationService::class)->create([
            'username' => 'valid.student',
            'first_name' => 'Valid',
            'last_name' => 'Student',
            'types' => [PersonType::STUDENT],
            'student_number' => 'STU-002',
        ]);

        $this->assertDatabaseHas('students', [
            'person_id' => $result['person']->id,
            'student_number' => 'STU-002',
        ]);

        $this->assertDatabaseHas('person_types', [
            'person_id' => $result['person']->id,
            'type' => PersonType::STUDENT,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $result['user']->id,
            'person_id' => $result['person']->id,
        ]);
    }

    private function createExistingUser(string $username): User
    {
        $person = Person::query()->create([
            'first_name' => 'Existing',
            'last_name' => 'User',
        ]);

        return User::query()->create([
            'person_id' => $person->id,
            'username' => $username,
            'password' => 'test-password',
            'status' => User::STATUS_ACTIVE,
            'is_it_admin' => false,
        ]);
    }
}