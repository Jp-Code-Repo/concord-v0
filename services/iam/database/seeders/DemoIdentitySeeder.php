<?php

namespace Database\Seeders;

use App\Models\Person;
use App\Models\PersonType;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoIdentitySeeder extends Seeder
{
    private const DEMO_PASSWORD = 'ConcordDemo!2026';

    public function run(): void
    {
        if (! config('concord.demo_seed_enabled')) {
            $this->command?->warn(
                'Demo identities skipped. Set IAM_DEMO_SEED=true to enable them.'
            );

            return;
        }

        DB::transaction(function (): void {
            $this->seedIdentity(
                username: 'demo.employee',
                firstName: 'Alex',
                lastName: 'Employee',
                email: 'alex.employee@concord.local',
                types: [PersonType::EMPLOYEE],
            );

            $this->seedStudent();

            $this->seedIdentity(
                username: 'demo.parent',
                firstName: 'Pat',
                lastName: 'Guardian',
                email: 'pat.guardian@concord.local',
                types: [PersonType::PARENT_GUARDIAN],
            );

            $this->seedIdentity(
                username: 'demo.visitor',
                firstName: 'Victor',
                lastName: 'Visitor',
                email: 'victor.visitor@concord.local',
                types: [PersonType::VISITOR],
            );

        });

        $this->command?->info('Demo identity seeding completed.');
    }

    private function seedStudent(): void
    {
        $person = $this->seedIdentity(
            username: 'demo.student',
            firstName: 'Sam',
            lastName: 'Student',
            email: 'sam.student@concord.local',
            types: [PersonType::STUDENT],
        );

        $student = Student::query()->firstOrCreate(
            ['person_id' => $person->id],
            ['student_number' => 'DEMO-STU-0001'],
        );

        StudentEnrollment::query()->firstOrCreate(
            [
                'student_id' => $student->id,
                'school_year' => '2026-2027',
            ],
            [
                'grade_level' => 'Grade 1',
                'section' => 'A',
                'status' => 'enrolled',
            ],
        );
    }

    /**
     * Create a demo person, login account, and their person types.
     * Existing records are retained when this seeder is rerun.
     *
     * @param list<string> $types
     */
    private function seedIdentity(
        string $username,
        string $firstName,
        string $lastName,
        string $email,
        array $types,
    ): Person {
        $person = Person::query()->firstOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'preferences' => [],
            ],
        );

        foreach ($types as $type) {
            $person->types()->firstOrCreate(['type' => $type]);
        }

        $user = User::query()->firstOrCreate(
            ['username' => $username],
            [
                'person_id' => $person->id,
                'password' => self::DEMO_PASSWORD,
                'status' => User::STATUS_ACTIVE,
                'is_it_admin' => false,
            ],
        );

        if ($user->person_id !== $person->id) {
            throw new \RuntimeException(
                "Demo username {$username} already belongs to another person."
            );
        }

        return $person;
    }
}
