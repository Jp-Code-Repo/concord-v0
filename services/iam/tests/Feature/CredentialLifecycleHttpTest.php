<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\User;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class CredentialLifecycleHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_password_change_works_through_the_http_pipeline(): void
    {
        $person = Person::query()->create([
            'first_name' => 'HTTP',
            'last_name' => 'Test',
        ]);

        $user = User::query()->create([
            'person_id' => $person->id,
            'username' => 'http-password-user',
            'password' => 'initial-password',
            'must_change_password' => true,
        ]);

        // Authenticate through the actual login endpoint.
        $loginResponse = $this->postJson('/api/iam/login', [
            'username' => 'http-password-user',
            'password' => 'initial-password',
        ]);

        $loginResponse->assertOk();

        $loginResponse->assertJsonPath('user.must_change_password', true);
        $loginResponse->assertJsonPath('access', null);

        // Extract the actual session cookie issued by the login endpoint.
        $sessionCookie = collect($loginResponse->headers->getCookies())
            ->first(
                fn (Cookie $cookie) =>
                    $cookie->getName() === SessionService::COOKIE
            );

        $this->assertNotNull($sessionCookie);

        $token = $sessionCookie->getValue();

        $this->assertNotEmpty($token);

        // A normal authenticated endpoint must be blocked.
        $blockedResponse = $this->call(
            'GET',
            '/api/iam/verify',
            [],
            [SessionService::COOKIE => $token],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );

        $blockedResponse->assertForbidden();

        $this->assertSame(
            'PASSWORD_CHANGE_REQUIRED',
            json_decode($blockedResponse->getContent(), true)['code']
        );

        // Change the password through the actual HTTP endpoint.
        $passwordResponse = $this->call(
            'POST',
            '/api/iam/me/password',
            [
                'current_password' => 'initial-password',
                'new_password' => 'replacement-password-123',
                'new_password_confirmation' => 'replacement-password-123',
            ],
            [SessionService::COOKIE => $token],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );

        $passwordResponse->assertOk();

        $passwordResponse->assertJsonPath('must_change_password', false);

        // The same session cookie must now access normal authenticated endpoints.
        $verifyResponse = $this->call(
            'GET',
            '/api/iam/verify',
            [],
            [SessionService::COOKIE => $token],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );

        $verifyResponse->assertNoContent();

        $this->assertSame(
            $user->id,
            $verifyResponse->headers->get('X-User-Id')
        );

        // Confirm the database reflects the completed password change.
        $this->assertFalse($user->fresh()->must_change_password);
    }
}
