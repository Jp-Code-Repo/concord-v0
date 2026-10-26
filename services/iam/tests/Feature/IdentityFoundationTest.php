<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonType;
use App\Models\Student;
use App\Models\User;
use App\Services\PersonService;
use App\Services\SessionService;
use App\Services\UserCreationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class IdentityFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $username, bool $administrator = false): User
    {
        $person = Person::query()->create([
            'first_name' => ucfirst($username),
            'last_name' => 'Test',
            'email' => $username.'@example.test',
            'preferences' => ['theme' => 'system'],
        ]);

        return User::query()->create([
            'person_id' => $person->id,
            'username' => $username,
            'password' => 'test-password',
            'status' => User::STATUS_ACTIVE,
            'is_it_admin' => $administrator,
        ]);
    }

    private function loginAs(User $user): string
    {
        $response = $this->postJson('/api/iam/login', [
            'username' => $user->username,
            'password' => 'test-password',
        ])->assertOk();

        return $response->getCookie(SessionService::COOKIE, false)->getValue();
    }

    public function test_person_and_user_are_separate_and_a_person_has_at_most_one_user(): void
    {
        $user = $this->makeUser('person-one');

        $this->assertNotSame($user->id, $user->person_id);
        $this->assertDatabaseHas('people', ['id' => $user->person_id]);

        $this->expectException(QueryException::class);
        User::query()->create([
            'person_id' => $user->person_id,
            'username' => 'duplicate-account',
            'password' => 'test-password',
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    public function test_person_can_be_created_without_an_account_then_provisioned_by_a_service(): void
    {
        $person = app(PersonService::class)->create([
            'first_name' => 'HRIS',
            'middle_name' => 'Provisioned',
            'last_name' => 'Employee',
            'email' => 'hris.employee@example.test',
        ]);

        $this->assertDatabaseHas('people', ['id' => $person->id]);
        $this->assertDatabaseMissing('users', ['person_id' => $person->id]);

        $result = app(UserCreationService::class)->provisionForPerson($person, 'hris.employee');

        $this->assertSame($person->id, $result['user']->person_id);
        $this->assertDatabaseHas('users', [
            'id' => $result['user']->id,
            'person_id' => $person->id,
            'username' => 'hris.employee',
        ]);
        $this->assertDatabaseHas('person_types', [
            'person_id' => $person->id,
            'type' => PersonType::EMPLOYEE,
        ]);
    }

    public function test_personal_preferences_are_saved_on_the_person(): void
    {
        $user = $this->makeUser('preference-user');
        $token = $this->loginAs($user);

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->patchJson('/api/iam/me/preferences', [
                'preferences' => ['theme' => 'dark', 'language' => 'en'],
            ])
            ->assertOk()
            ->assertJsonPath('preferences.theme', 'dark')
            ->assertJsonPath('preferences.language', 'en');


            $this->assertSame(
                ['theme' => 'dark', 'language' => 'en'],
                json_decode(
                    DB::table('people')
                        ->where('id', $user->person_id)
                        ->value('preferences'),
                    true
                )
            );

    }

    public function test_session_settings_are_saved_on_the_user_account(): void
    {
        $user = $this->makeUser('settings-user');
        $token = $this->loginAs($user);

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->patchJson('/api/iam/me/settings', [
                'settings' => ['session_warning' => false],
            ])
            ->assertOk()
            ->assertJsonPath('settings.session_warning', false);


        $this->assertSame(
            ['session_warning' => false],
            json_decode(
                DB::table('users')
                    ->where('id', $user->id)
                    ->value('settings'),
                true
            )
        );

    }

    public function test_student_identity_and_enrollment_history_are_separate(): void
    {
        $created = app(UserCreationService::class)->create([
            'username' => 'student.one',
            'first_name' => 'Student',
            'last_name' => 'One',
            'email' => null,
            'types' => [PersonType::STUDENT],
            'student_number' => 'STU-0001',
        ]);

        $student = Student::query()->where('person_id', $created['person']->id)->firstOrFail();
        $student->enrollments()->create([
            'school_year' => '2025-2026',
            'grade_level' => 'Grade 1',
            'section' => 'A',
            'status' => 'promoted',
        ]);
        $student->enrollments()->create([
            'school_year' => '2026-2027',
            'grade_level' => 'Grade 2',
            'section' => 'B',
            'status' => 'enrolled',
            'guardian_import_data' => [
                ['name' => 'Parent One', 'contact_number' => '09170000000'],
            ],
        ]);

        $this->assertCount(2, $student->fresh()->enrollments);
        $this->assertDatabaseHas('student_enrollments', [
            'student_id' => $student->id,
            'school_year' => '2025-2026',
            'grade_level' => 'Grade 1',
        ]);
      
        $this->assertSame(
            [['name' => 'Parent One', 'contact_number' => '09170000000']],
            json_decode(
                DB::table('student_enrollments')
                    ->where('student_id', $student->id)
                    ->where('school_year', '2026-2027')
                    ->value('guardian_import_data'),
                true
            )
        );

    }
}
