<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PlatformAdministrator;
use App\Models\User;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class UserAccountReactivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            \Illuminate\Routing\Middleware\ThrottleRequests::class
        );
    }

    public function test_administrator_can_reactivate_a_disabled_account(): void
    {
        [, $cookie] = $this->createAdministrator();

        $user = $this->createUser('disabled.user', [
            'status' => User::STATUS_DISABLED,
            'disabled_at' => now(),
        ]);

        $response = $this->call(
            'POST',
            "/api/iam/admin/users/{$user->id}/reactivate",
            [],
            [SessionService::COOKIE => $cookie],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );

        $response->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'identity.user_reactivated',
            'entity_type' => 'user',
            'entity_id' => $user->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => User::STATUS_ACTIVE,
            'disabled_at' => null,
        ]);
    }

    public function test_administrator_can_reinstate_a_deactivated_account(): void
    {
        [, $cookie] = $this->createAdministrator();

        $user = $this->createUser('reinstated.user', [
            'status' => User::STATUS_DEACTIVATED,
            'deactivated_at' => now(),
        ]);

        $response = $this->call(
            'POST',
            "/api/iam/admin/users/{$user->id}/reinstate",
            [],
            [SessionService::COOKIE => $cookie],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );

        $response->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'identity.user_reinstated',
            'entity_type' => 'user',
            'entity_id' => $user->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => User::STATUS_ACTIVE,
            'deactivated_at' => null,
        ]);
    }

    public function test_reactivation_endpoint_only_accepts_disabled_accounts(): void
    {
        [, $cookie] = $this->createAdministrator();

        $user = $this->createUser('deactivated.user', [
            'status' => User::STATUS_DEACTIVATED,
            'deactivated_at' => now(),
        ]);

        $response = $this->call(
            'POST',
            "/api/iam/admin/users/{$user->id}/reactivate",
            [],
            [SessionService::COOKIE => $cookie],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );

        $response->assertStatus(409);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => User::STATUS_DEACTIVATED,
        ]);
    }

    public function test_active_account_cannot_be_reactivated_again(): void
    {
        [, $cookie] = $this->createAdministrator();

        $user = $this->createUser('active.user', [
            'status' => User::STATUS_ACTIVE,
        ]);

        $response = $this->call(
            'POST',
            "/api/iam/admin/users/{$user->id}/reactivate",
            [],
            [SessionService::COOKIE => $cookie],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );

        $response->assertStatus(409);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    private function createAdministrator(): array
    {
        $admin = $this->createUser('administrator');

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

    private function createUser(
        string $username,
        array $attributes = []
    ): User {
        $person = Person::query()->create([
            'first_name' => ucfirst($username),
            'last_name' => 'Test',
        ]);

        $user = new User(array_merge([
            'person_id' => $person->id,
            'username' => $username,
            'password' => 'test-password',
            'status' => User::STATUS_ACTIVE,
            'is_it_admin' => false,
        ], $attributes));

        $user->save();

        return $user;
    }
}
