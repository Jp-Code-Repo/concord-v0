<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PlatformAdministrator;
use App\Models\User;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_generate_a_temporary_password(): void
    {
        [$admin, $cookie] = $this->createAdministrator();

        $target = $this->createUser('target.user');
        $session = $this->createUserSession($target);

        $response = $this->resetPassword($target->id, [
            'mode' => 'generate',
        ], $cookie);

        $response->assertOk()
            ->assertJsonPath('message', 'Password reset successfully.')
            ->assertJsonStructure([
                'temporary_password',
            ]);

        $temporaryPassword = $response->json('temporary_password');

        $this->assertIsString($temporaryPassword);
        $this->assertGreaterThanOrEqual(12, strlen($temporaryPassword));

        $updatedUser = $target->fresh();

        $this->assertTrue(
            Hash::check($temporaryPassword, $updatedUser->password)
        );

        $this->assertTrue($updatedUser->must_change_password);

        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertSame(
            'password_reset',
            $session->fresh()->revoked_reason
        );

        $this->assertNull(
            app(SessionService::class)->resolve(
                $this->sessionTokens[$target->id]
            )
        );

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'identity.user_password_reset',
            'entity_type' => 'user',
            'entity_id' => $target->id,
        ]);
    }

    public function test_administrator_can_set_a_custom_temporary_password(): void
    {
        [$admin, $cookie] = $this->createAdministrator();

        $target = $this->createUser('custom.target');
        $session = $this->createUserSession($target);

        $response = $this->resetPassword($target->id, [
            'mode' => 'custom',
            'password' => 'administrator-chosen-password',
        ], $cookie);

        $response->assertOk()
            ->assertJsonPath('message', 'Password reset successfully.')
            ->assertJsonPath(
                'temporary_password',
                'administrator-chosen-password'
            );

        $updatedUser = $target->fresh();

        $this->assertTrue(
            Hash::check(
                'administrator-chosen-password',
                $updatedUser->password
            )
        );

        $this->assertTrue($updatedUser->must_change_password);

        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertSame(
            'password_reset',
            $session->fresh()->revoked_reason
        );

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'identity.user_password_reset',
            'entity_type' => 'user',
            'entity_id' => $target->id,
        ]);
    }

    public function test_custom_password_must_meet_minimum_length(): void
    {
        [, $cookie] = $this->createAdministrator();

        $target = $this->createUser('short.password');

        $response = $this->resetPassword($target->id, [
            'mode' => 'custom',
            'password' => 'short123',
        ], $cookie);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertTrue(
            Hash::check('test-password', $target->fresh()->password)
        );

        $this->assertFalse($target->fresh()->must_change_password);
    }

    public function test_invalid_reset_mode_is_rejected(): void
    {
        [, $cookie] = $this->createAdministrator();

        $target = $this->createUser('invalid.mode');

        $response = $this->resetPassword($target->id, [
            'mode' => 'unknown',
        ], $cookie);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['mode']);
    }

    private array $sessionTokens = [];

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

    private function createUser(string $username): User
    {
        $person = Person::query()->create([
            'first_name' => ucfirst($username),
            'last_name' => 'Test',
        ]);

        return User::query()->create([
            'person_id' => $person->id,
            'username' => $username,
            'password' => 'test-password',
            'status' => User::STATUS_ACTIVE,
            'is_it_admin' => false,
        ]);
    }

    private function createUserSession(User $user)
    {
        [$token, $session] = app(SessionService::class)->create(
            $user,
            Request::create('/api/iam/login', 'POST')
        );

        $this->sessionTokens[$user->id] = $token;

        // Resolve once to populate the session cache.
        $this->assertNotNull(
            app(SessionService::class)->resolve($token)
        );

        return $session;
    }

    private function resetPassword(
        string $userId,
        array $data,
        string $cookie
    ) {
        return $this->call(
            'POST',
            "/api/iam/admin/users/{$userId}/password",
            $data,
            [SessionService::COOKIE => $cookie],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );
    }
}