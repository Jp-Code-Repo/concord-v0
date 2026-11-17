<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\User;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;

class AuthenticationStatusResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_login_returns_invalid_credentials_code_for_wrong_password_on_disabled_account(): void
    {
        $user = $this->createUser('disabled-wrong-password', User::STATUS_DISABLED);

        $this->postJson('/api/iam/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ])->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Invalid credentials.',
                'code' => 'INVALID_CREDENTIALS',
            ]);
    }

    public function test_login_returns_invalid_credentials_code_for_unknown_username(): void
    {
        $this->postJson('/api/iam/login', [
            'username' => 'unknown-user',
            'password' => 'wrong-password',
        ])->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Invalid credentials.',
                'code' => 'INVALID_CREDENTIALS',
            ]);
    }

    public function test_login_returns_account_disabled_code_after_validating_password(): void
    {
        $user = $this->createUser('disabled-correct-password', User::STATUS_DISABLED);

        $this->postJson('/api/iam/login', [
            'username' => $user->username,
            'password' => 'test-password',
        ])->assertForbidden()
            ->assertExactJson([
                'message' => 'Your account has been disabled. Please contact your system administrator.',
                'code' => 'ACCOUNT_DISABLED',
            ]);
    }

    public function test_login_returns_account_deactivated_code_after_validating_password(): void
    {
        $user = $this->createUser('deactivated-correct-password', User::STATUS_DEACTIVATED);

        $this->postJson('/api/iam/login', [
            'username' => $user->username,
            'password' => 'test-password',
        ])->assertForbidden()
            ->assertExactJson([
                'message' => 'Your account has been deactivated. Please contact your system administrator.',
                'code' => 'ACCOUNT_DEACTIVATED',
            ]);
    }

    public function test_me_returns_disabled_code_for_session_revoked_by_account_disable(): void
    {
        $this->assertLifecycleRevocationResponse(
            'account_disabled',
            User::STATUS_ACTIVE,
            'ACCOUNT_DISABLED',
            'Your account has been disabled. Please contact your system administrator.'
        );
    }

    public function test_me_returns_deactivated_code_for_session_revoked_by_account_deactivation(): void
    {
        $this->assertLifecycleRevocationResponse(
            'account_deactivated',
            User::STATUS_ACTIVE,
            'ACCOUNT_DEACTIVATED',
            'Your account has been deactivated. Please contact your system administrator.'
        );
    }

    public function test_me_keeps_generic_response_for_logged_out_session(): void
    {
        $this->assertGenericUnauthenticatedResponse('logout');
    }

    public function test_me_keeps_generic_response_for_expired_session(): void
    {
        $user = $this->createUser('expired-session-user');
        [$token, $session] = app(SessionService::class)->create(
            $user,
            request()
        );

        $session->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->call(
            'GET',
            '/api/iam/me',
            [],
            [SessionService::COOKIE => $token],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        )->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_me_keeps_generic_response_for_missing_session(): void
    {
        $this->getJson('/api/iam/me')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_current_account_status_does_not_override_ordinary_revocation_reason(): void
    {
        $this->assertLifecycleRevocationResponse(
            'logout',
            User::STATUS_DISABLED,
            null,
            null
        );
    }

    private function assertLifecycleRevocationResponse(
        string $reason,
        string $status,
        ?string $expectedCode,
        ?string $expectedMessage,
    ): void {
        $user = $this->createUser('session-'.str_replace('_', '-', $reason), $status);
        [$token, $session] = app(SessionService::class)->create($user, request());

        app(SessionService::class)->revoke($session->id, $reason, $token);

        $response = $this->call(
            'GET',
            '/api/iam/me',
            [],
            [SessionService::COOKIE => $token],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );

        $response->assertUnauthorized();

        if ($expectedCode === null) {
            $response->assertExactJson(['message' => 'Unauthenticated.']);

            return;
        }

        $response->assertExactJson([
            'message' => $expectedMessage,
            'code' => $expectedCode,
        ]);
    }

    private function assertGenericUnauthenticatedResponse(string $reason): void
    {
        $user = $this->createUser('ordinary-'.str_replace('_', '-', $reason));
        [$token, $session] = app(SessionService::class)->create($user, request());

        app(SessionService::class)->revoke($session->id, $reason, $token);

       $this->call(
            'GET',
            '/api/iam/me',
            [],
            [SessionService::COOKIE => $token],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        )->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    private function createUser(string $username, string $status = User::STATUS_ACTIVE): User
    {
        $person = Person::query()->create([
            'first_name' => 'Authentication',
            'last_name' => 'Test',
        ]);

        return User::query()->create([
            'person_id' => $person->id,
            'username' => $username,
            'password' => 'test-password',
            'status' => $status,
        ]);
    }
}
