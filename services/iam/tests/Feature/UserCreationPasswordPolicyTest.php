<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonType;
use App\Services\UserCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCreationPasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_user_must_change_generated_initial_password(): void
    {
        $result = app(UserCreationService::class)->create([
            'username' => 'new.employee',
            'first_name' => 'New',
            'last_name' => 'Employee',
            'types' => [PersonType::EMPLOYEE],
        ]);

        $this->assertTrue($result['user']->must_change_password);
    }

    public function test_provisioned_user_must_change_generated_initial_password(): void
    {
        $person = Person::query()->create([
            'first_name' => 'Existing',
            'last_name' => 'Employee',
        ]);

        $result = app(UserCreationService::class)->provisionForPerson(
            $person,
            'existing.employee',
            [PersonType::EMPLOYEE]
        );

        $this->assertTrue($result['user']->must_change_password);
    }
}
