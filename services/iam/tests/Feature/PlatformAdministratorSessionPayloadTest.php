<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PlatformAdministrator;
use App\Models\User;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class PlatformAdministratorSessionPayloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_login_identifies_the_designated_administrator(): void
    {
        $admin = $this->createUser('designated-admin', false);
        $this->designateAdministrator($admin);

        $response = $this->login($admin);

        $response->assertOk()
            ->assertJsonPath(
                'user.platform_authority.is_administrator',
                true
            )
            ->assertJsonPath('user.is_it_admin', false);
    }

    public function test_login_does_not_infer_authority_from_legacy_admin_flag(): void
    {
        $user = $this->createUser('legacy-admin', true);

        $response = $this->login($user);

        $response->assertOk()
            ->assertJsonPath(
                'user.platform_authority.is_administrator',
                false
            )
            ->assertJsonPath('user.is_it_admin', true);
    }

    public function test_me_identifies_the_designated_administrator(): void
    {
        $admin = $this->createUser('designated-admin-me', false);
        $this->designateAdministrator($admin);

        $cookie = $this->sessionCookie($this->login($admin));

        $response = $this->call(
            'GET',
            '/api/iam/me',
            [],
            [SessionService::COOKIE => $cookie],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );

        $response->assertOk()
            ->assertJsonPath(
                'user.platform_authority.is_administrator',
                true
            )
            ->assertJsonPath('user.is_it_admin', false);
    }

    public function test_me_does_not_infer_authority_from_legacy_admin_flag(): void
    {
        $user = $this->createUser('legacy-admin-me', true);

        $cookie = $this->sessionCookie($this->login($user));

        $response = $this->call(
            'GET',
            '/api/iam/me',
            [],
            [SessionService::COOKIE => $cookie],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );

        $response->assertOk()
            ->assertJsonPath(
                'user.platform_authority.is_administrator',
                false
            )
            ->assertJsonPath('user.is_it_admin', true);
    }

    private function createUser(
        string $username,
        bool $legacyAdmin
    ): User {
        $person = Person::query()->create([
            'first_name' => ucfirst($username),
            'last_name' => 'Test',
        ]);

        return User::query()->create([
            'person_id' => $person->id,
            'username' => $username,
            'password' => 'test-password',
            'status' => User::STATUS_ACTIVE,
            'is_it_admin' => $legacyAdmin,
        ]);
    }

    private function designateAdministrator(User $user): void
    {
        $designation = new PlatformAdministrator();
        $designation->id = 1;
        $designation->user_id = $user->id;
        $designation->save();
    }

    private function login(User $user)
    {
        return $this->postJson('/api/iam/login', [
            'username' => $user->username,
            'password' => 'test-password',
        ]);
    }

    private function sessionCookie($response): string
    {
        $cookie = collect($response->headers->getCookies())
            ->first(
                fn (Cookie $cookie) =>
                    $cookie->getName() === SessionService::COOKIE
            );

        $this->assertNotNull($cookie);

        return $cookie->getValue();
    }
}
