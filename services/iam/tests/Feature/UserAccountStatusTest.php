<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PlatformAdministrator;
use App\Models\User;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class UserAccountStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            \Illuminate\Routing\Middleware\ThrottleRequests::class
        );
    }

    public function test_administrator_can_disable_an_active_account(): void
    {
        [, $cookie] = $this->createAdministrator();

        $user = $this->createUser('disabled.user');

        $response = $this->changeStatus(
            'disable',
            $user->id,
            $cookie
        );

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => User::STATUS_DISABLED,
        ]);

        $this->assertNotNull($user->fresh()->disabled_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'identity.user_disabled',
            'entity_type' => 'user',
            'entity_id' => $user->id,
        ]);
    }

    public function test_administrator_can_deactivate_an_active_account(): void
    {
        [, $cookie] = $this->createAdministrator();

        $user = $this->createUser('deactivated.user');

        $response = $this->changeStatus(
            'deactivate',
            $user->id,
            $cookie
        );

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => User::STATUS_DEACTIVATED,
        ]);

        $this->assertNotNull($user->fresh()->deactivated_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'identity.user_deactivated',
            'entity_type' => 'user',
            'entity_id' => $user->id,
        ]);
    }

    public function test_already_disabled_account_cannot_be_disabled_again(): void
    {
        [, $cookie] = $this->createAdministrator();

        $user = $this->createUser('already.disabled', [
            'status' => User::STATUS_DISABLED,
            'disabled_at' => now(),
        ]);

        $this->changeStatus('disable', $user->id, $cookie)
            ->assertStatus(409);
    }

    public function test_already_deactivated_account_cannot_be_deactivated_again(): void
    {
        [, $cookie] = $this->createAdministrator();

        $user = $this->createUser('already.deactivated', [
            'status' => User::STATUS_DEACTIVATED,
            'deactivated_at' => now(),
        ]);

        $this->changeStatus('deactivate', $user->id, $cookie)
            ->assertStatus(409);
    }

    public function test_designated_administrator_cannot_be_disabled_or_deactivated(): void
    {
        [$admin, $cookie] = $this->createAdministrator();

        $this->changeStatus('disable', $admin->id, $cookie)
            ->assertStatus(409);

        $this->changeStatus('deactivate', $admin->id, $cookie)
            ->assertStatus(409);
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

    private function changeStatus(
        string $action,
        string $userId,
        string $cookie
    ) {
        return $this->call(
            'POST',
            "/api/iam/admin/users/{$userId}/{$action}",
            [],
            [SessionService::COOKIE => $cookie],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );
    }
}