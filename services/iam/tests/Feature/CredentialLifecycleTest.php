<?php

namespace Tests\Feature;

use App\Http\Controllers\AuthController;
use App\Http\Middleware\SessionAuth;
use App\Models\AuthSession;
use App\Models\Person;
use App\Models\User;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CredentialLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_resolution_includes_password_change_requirement(): void
    {
        $user = $this->createUser('password-required-user');
        $user->must_change_password = true;
        $user->save();

        $request = Request::create('/api/iam/login', 'POST');

        [$token] = app(SessionService::class)->create($user, $request);

        $auth = app(SessionService::class)->resolve($token);

        $this->assertNotNull($auth);
        $this->assertSame($user->id, $auth['user_id']);
        $this->assertTrue($auth['must_change_password']);
    }

    public function test_password_change_required_user_is_blocked_from_normal_endpoints(): void
    {
        $user = $this->createUser('blocked-user');
        $user->must_change_password = true;
        $user->save();

        $request = Request::create('/api/iam/me', 'GET');

        [$token] = app(SessionService::class)->create($user, $request);

        $request->cookies->set(SessionService::COOKIE, $token);

        $middleware = app(SessionAuth::class);

        $response = $middleware->handle(
            $request,
            fn ($request) => response()->json(['message' => 'Authorized.'])
        );

        $this->assertSame(403, $response->getStatusCode());

        $this->assertSame(
            'PASSWORD_CHANGE_REQUIRED',
            json_decode($response->getContent(), true)['code']
        );
    }

    public function test_password_change_and_logout_are_allowed_when_password_change_is_required(): void
    {
        $user = $this->createUser('allowed-user');
        $user->must_change_password = true;
        $user->save();

        $request = Request::create('/api/iam/me/password', 'POST');

        [$token] = app(SessionService::class)->create($user, $request);

        $request->cookies->set(SessionService::COOKIE, $token);

        $middleware = app(SessionAuth::class);

        $passwordResponse = $middleware->handle(
            $request,
            fn ($request) => response()->json(['message' => 'Allowed.'])
        );

        $this->assertSame(200, $passwordResponse->getStatusCode());

        $logoutRequest = Request::create('/api/iam/logout', 'POST');
        $logoutRequest->cookies->set(SessionService::COOKIE, $token);

        $logoutResponse = $middleware->handle(
            $logoutRequest,
            fn ($request) => response()->json(['message' => 'Allowed.'])
        );

        $this->assertSame(200, $logoutResponse->getStatusCode());
    }

    public function test_password_change_rejects_an_incorrect_current_password(): void
    {
        $user = $this->createUser('incorrect-password-user');

        $request = Request::create('/api/iam/me/password', 'POST', [
            'current_password' => 'incorrect-password',
            'new_password' => 'new-secure-password',
            'new_password_confirmation' => 'new-secure-password',
        ]);

        $request->attributes->set('auth', [
            'user_id' => $user->id,
            'session_id' => 'test-session',
        ]);

        $response = app(AuthController::class)->changePassword(
            $request,
            app(SessionService::class)
        );

        $this->assertSame(422, $response->getStatusCode());

        $this->assertArrayHasKey(
            'current_password',
            json_decode($response->getContent(), true)['errors']
        );

        $this->assertTrue(
            Hash::check('test-password', $user->fresh()->password)
        );
    }

    public function test_password_change_rejects_passwords_shorter_than_twelve_characters(): void
    {
        $user = $this->createUser('short-password-user');

        $request = Request::create('/api/iam/me/password', 'POST', [
            'current_password' => 'test-password',
            'new_password' => 'short123',
            'new_password_confirmation' => 'short123',
        ]);

        $request->attributes->set('auth', [
            'user_id' => $user->id,
            'session_id' => 'test-session',
        ]);

        try {
            app(AuthController::class)->changePassword(
                $request,
                app(SessionService::class)
            );

            $this->fail('Expected password validation to reject a short password.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'new_password',
                $exception->errors()
            );
        }

        $this->assertTrue(
            Hash::check('test-password', $user->fresh()->password)
        );
    }

    public function test_successful_password_change_preserves_current_session_and_revokes_other_sessions(): void
    {
        $user = $this->createUser('successful-password-user');
        $user->must_change_password = true;
        $user->save();

        $sessions = app(SessionService::class);

        $currentRequest = Request::create('/api/iam/login', 'POST');
        [$currentToken, $currentSession] = $sessions->create($user, $currentRequest);

        $otherRequest = Request::create('/api/iam/login', 'POST');
        [$otherToken, $otherSession] = $sessions->create($user, $otherRequest);

        // Resolve both sessions before changing the password to populate the cache.
        $this->assertTrue($sessions->resolve($currentToken)['must_change_password']);
        $this->assertTrue($sessions->resolve($otherToken)['must_change_password']);

        $request = Request::create('/api/iam/me/password', 'POST', [
            'current_password' => 'test-password',
            'new_password' => 'new-secure-password-123',
            'new_password_confirmation' => 'new-secure-password-123',
        ]);

        $request->cookies->set(SessionService::COOKIE, $currentToken);

        $request->attributes->set('auth', [
            'user_id' => $user->id,
            'session_id' => $currentSession->id,
        ]);

        $response = app(AuthController::class)->changePassword(
            $request,
            $sessions
        );

        $this->assertSame(200, $response->getStatusCode());

        $this->assertFalse(
            json_decode($response->getContent(), true)['must_change_password']
        );

        $updatedUser = $user->fresh();

        $this->assertTrue(
            Hash::check('new-secure-password-123', $updatedUser->password)
        );

        $this->assertFalse($updatedUser->must_change_password);

        // Current device remains authenticated.
        $this->assertNull($currentSession->fresh()->revoked_at);

        $currentAuth = $sessions->resolve($currentToken);

        $this->assertNotNull($currentAuth);
        $this->assertFalse($currentAuth['must_change_password']);

        // Other devices are revoked.
        $this->assertNotNull($otherSession->fresh()->revoked_at);

        $this->assertSame(
            'password_changed',
            $otherSession->fresh()->revoked_reason
        );

        $this->assertNull($sessions->resolve($otherToken));

        // Verify the password-change audit event.
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'auth.password_changed',
        ]);
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
        ]);
    }
}
