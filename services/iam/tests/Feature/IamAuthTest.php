<?php

namespace Tests\Feature;

use App\Models\Person;
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
        $person = Person::create([
            'first_name' => 'Juan',
            'middle_name' => 'Dela',
            'last_name' => 'Cruz',
            'email' => 'juan@example.test',
            'preferences' => [],
        ]);

        return User::create([
            'person_id' => $person->id,
            'username' => 'juan',
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


    public function test_login_returns_the_same_payload_as_me(): void
    {
        $this->makeUser();

        $loginPayload = $this->postJson('/api/iam/login', [
            'username' => 'juan',
            'password' => 'correct-horse-battery',
        ])->assertOk()
          ->assertJsonStructure(['user' => ['id', 'username', 'person'], 'access'])
          ->json();

        $cookie = $this->postJson('/api/iam/login', [
            'username' => 'juan',
            'password' => 'correct-horse-battery',
        ])->getCookie(SessionService::COOKIE, false)->getValue();

        $mePayload = $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $cookie)
            ->getJson('/api/iam/me')
            ->assertOk()
            ->json();

        // Login must not need a follow-up /me: the two payloads are identical.
        $this->assertSame($mePayload, $loginPayload);
    }

    public function test_logout_revokes_the_session_immediately_even_when_cached(): void
    {
        $this->makeUser();
        $token = $this->login();

        // Warm the Redis/array cache entry so revocation has something to clear.
        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->getJson('/api/iam/me')
            ->assertOk();

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson('/api/iam/logout')
            ->assertStatus(204);

        $this->assertDatabaseHas('auth_sessions', [
            'token_hash' => hash('sha256', $token),
            'revoked_reason' => 'logout',
        ]);

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->getJson('/api/iam/me')
            ->assertStatus(401);
    }

    public function test_revoke_with_a_mismatched_token_never_revokes_the_wrong_session(): void
    {
        $this->makeUser();
        $token = $this->login();
        $sessions = app(SessionService::class);
        $row = \DB::table('auth_sessions')->where('token_hash', hash('sha256', $token))->first();

        $this->assertNotNull($sessions->resolve($token)); // warms the cache entry

        // A token that does not belong to this session id falls back to the lookup
        // path: the session is still revoked by id, and its own cache entry is cleared.
        $sessions->revoke($row->id, 'logout', str_repeat('a', 64));

        $this->assertNull($sessions->resolve($token));
        $this->assertDatabaseHas('auth_sessions', ['id' => $row->id, 'revoked_reason' => 'logout']);
    }
    
}