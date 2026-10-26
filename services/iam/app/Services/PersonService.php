<?php

namespace App\Services;

use App\Models\Person;
use Illuminate\Support\Str;

/** Creates basic identity records for IAM and trusted service integrations. */
class PersonService
{
    public function create(array $attributes): Person
    {
        return Person::query()->create([
            'first_name' => trim($attributes['first_name']),
            'middle_name' => $this->nullableTrim($attributes['middle_name'] ?? null),
            'last_name' => trim($attributes['last_name']),
            'name_suffix' => $this->nullableTrim($attributes['name_suffix'] ?? null),
            'gender' => $this->nullableTrim($attributes['gender'] ?? null),
            'email' => $this->nullableLower($attributes['email'] ?? null),
            'preferences' => [],
        ]);
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
