<?php

namespace App\Services;

use App\Models\Person;
use App\Models\PersonType;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserCreationService
{
    /**
     * Create a person and their single linked login account atomically.
     * The generated initial password is returned once and only its hash is stored.
     *
     * @return array{person: Person, user: User, initial_password: string}
     */
    public function create(array $attributes): array
    {
        $username = trim($attributes['username']);
        $types = array_values(array_unique($attributes['types'] ?? [PersonType::VISITOR]));

        if (User::query()->where('username', $username)->exists()) {
            throw ValidationException::withMessages([
                'username' => ['This username is already taken.'],
            ]);
        }

        $invalidTypes = array_diff($types, PersonType::TYPES);
        if ($invalidTypes) {
            throw ValidationException::withMessages([
                'types' => ['One or more selected person types are invalid.'],
            ]);
        }

        if (in_array(PersonType::STUDENT, $types, true)) {
            $studentNumber = trim((string) ($attributes['student_number'] ?? ''));
            if ($studentNumber === '') {
                throw ValidationException::withMessages([
                    'student_number' => ['A student number is required for a student.'],
                ]);
            }

            if (Student::query()->where('student_number', $studentNumber)->exists()) {
                throw ValidationException::withMessages([
                    'student_number' => ['This student number is already registered.'],
                ]);
            }
        }

        $initialPassword = Str::password(16);

        [$person, $user] = DB::transaction(function () use ($attributes, $username, $types, $initialPassword) {
            $person = Person::query()->create([
                'first_name' => trim($attributes['first_name']),
                'middle_name' => $this->nullableTrim($attributes['middle_name'] ?? null),
                'last_name' => trim($attributes['last_name']),
                'name_suffix' => $this->nullableTrim($attributes['name_suffix'] ?? null),
                'gender' => $this->nullableTrim($attributes['gender'] ?? null),
                'email' => $this->nullableLower($attributes['email'] ?? null),
                'preferences' => [],
            ]);

            foreach ($types as $type) {
                $person->types()->create(['type' => $type]);
            }

            if (in_array(PersonType::STUDENT, $types, true)) {
                $person->student()->create([
                    'student_number' => trim($attributes['student_number']),
                ]);
            }

            $user = User::query()->create([
                'person_id' => $person->id,
                'username' => $username,
                'password' => $initialPassword,
                'status' => User::STATUS_ACTIVE,
                'is_it_admin' => false,
            ]);

            return [$person, $user];
        });

        return [
            'person' => $person->fresh(['types', 'student']),
            'user' => $user->fresh('person'),
            'initial_password' => $initialPassword,
        ];
    }

    /**
     * Provision an account for a person created by HRIS or another trusted service.
     * This path deliberately does not create a second person record.
     *
     * @param list<string> $types
     * @return array{person: Person, user: User, initial_password: string}
     */
    public function provisionForPerson(Person $person, string $username, array $types = [PersonType::EMPLOYEE]): array
    {
        $username = trim($username);
        $types = array_values(array_unique($types));

        if (User::query()->where('username', $username)->exists()) {
            throw ValidationException::withMessages([
                'username' => ['This username is already taken.'],
            ]);
        }

        if ($person->user()->exists()) {
            throw ValidationException::withMessages([
                'person_id' => ['This person already has a Concord account.'],
            ]);
        }

        $invalidTypes = array_diff($types, PersonType::TYPES);
        if ($invalidTypes) {
            throw ValidationException::withMessages([
                'types' => ['One or more selected person types are invalid.'],
            ]);
        }

        $initialPassword = Str::password(16);

        $user = DB::transaction(function () use ($person, $username, $types, $initialPassword): User {
            foreach ($types as $type) {
                $person->types()->firstOrCreate(['type' => $type]);
            }

            return User::query()->create([
                'person_id' => $person->id,
                'username' => $username,
                'password' => $initialPassword,
                'status' => User::STATUS_ACTIVE,
                'is_it_admin' => false,
            ]);
        });

        return [
            'person' => $person->fresh(['types', 'student']),
            'user' => $user->fresh('person'),
            'initial_password' => $initialPassword,
        ];
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableLower(?string $value): ?string
    {
        $value = $this->nullableTrim($value);

        return $value === null ? null : Str::lower($value);
    }
}
