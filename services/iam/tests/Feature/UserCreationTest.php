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

}
