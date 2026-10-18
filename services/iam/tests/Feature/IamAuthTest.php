<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;

class IamAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests cover authentication and sessions, not rate limiting.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function makeUser(string $status = 'active'): User
    {
        return User::create([
            'username' => 'juan',
            'email' => 'juan@example.test',
            'display_name' => 'Juan Dela Cruz',
            'password' => 'correct-horse-battery',
            'status' => $status,
        ]);
    }

    /** Logs in and returns the plain session token from the cookie. */
    private function login(): string
    {
        $response = $this->postJson('/api/iam/login', [
            'username' => 'juan',
            'password' => 'correct-horse-battery',
        ])->assertOk()
          ->assertCookie(SessionService::COOKIE);

        $token = $response->getCookie(
            SessionService::COOKIE,
            false
        )->getValue();

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            $token,
            'The login response did not provide the expected plain session token.'
        );

        $this->assertDatabaseHas('auth_sessions', [
            'token_hash' => hash('sha256', $token),
        ]);

        return $token;
    }

    public function test_login_then_me(): void
    {
        $this->makeUser();

        $token = $this->login();

        $this->assertNotNull(
            app(SessionService::class)->resolve($token),
            'The session exists in the database but cannot be resolved.'
        );

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->getJson('/api/iam/me')
            ->assertOk()
            ->assertJsonPath('user.username', 'juan');
    }

    public function test_wrong_password_and_unknown_user_get_the_same_answer(): void
    {
        $this->makeUser();

        $this->postJson('/api/iam/login', [
            'username' => 'juan',
            'password' => 'nope',
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid credentials.');

        $this->postJson('/api/iam/login', [
            'username' => 'ghost',
            'password' => 'nope',
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_disabled_account_cannot_log_in(): void
    {
        $this->makeUser('disabled');

        $this->postJson('/api/iam/login', [
            'username' => 'juan',
            'password' => 'correct-horse-battery',
        ])->assertStatus(401);
    }

    public function test_revoking_sessions_takes_effect_immediately_even_when_cached(): void
    {
        $user = $this->makeUser();
        $token = $this->login();

        // First request verifies access before revocation.
        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->getJson('/api/iam/me')
            ->assertOk();

        app(SessionService::class)->revokeAllForUser(
            $user->id,
            'account_disabled'
        );

        // The same token must no longer authorize requests.
        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->getJson('/api/iam/me')
            ->assertStatus(401);
    }

    public function test_verify_returns_identity_headers_for_the_edge(): void
    {
        $user = $this->makeUser();
        $token = $this->login();

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->getJson('/api/iam/verify')
            ->assertStatus(204)
            ->assertHeader('X-User-Id', $user->id);
    }

    public function test_edge_check_refuses_callers_the_edge_did_not_vouch_for(): void
    {
        $this->getJson('/api/iam/edge-check')
            ->assertStatus(401);
    }

    
}