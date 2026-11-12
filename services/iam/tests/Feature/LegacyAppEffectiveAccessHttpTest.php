<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\User;
use App\Models\UserLegacyAppGrant;
use App\Services\SessionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class LegacyAppEffectiveAccessHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        Schema::connection('concord_platform')
            ->dropIfExists('legacy_apps');

        Schema::connection('concord_platform')
            ->create('legacy_apps', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('name', 100);
                $table->string('url', 2048);
                $table->string('icon', 100)->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
    }

    public function test_login_returns_only_the_users_active_legacy_app_grants(): void
    {
        $user = $this->createUser('effective-access-user');

        $grantedApp = $this->createLegacyApp('Granted Application');
        $ungrantedApp = $this->createLegacyApp('Unassigned Application');

        $this->createGrant($user, $grantedApp);

        $response = $this->login($user);

        $response->assertOk()
            ->assertJsonPath('access.legacy_apps.0.id', $grantedApp)
            ->assertJsonPath('access.legacy_apps.0.name', 'Granted Application');

        $this->assertCount(
            1,
            $response->json('access.legacy_apps'),
        );

        $this->assertNotContains(
            $ungrantedApp,
            collect($response->json('access.legacy_apps'))
                ->pluck('id')
                ->all(),
        );
    }

    public function test_me_returns_the_same_effective_legacy_app_access_as_login(): void
    {
        $user = $this->createUser('me-effective-access-user');

        $firstApp = $this->createLegacyApp('First Application');
        $secondApp = $this->createLegacyApp('Second Application');

        $this->createGrant($user, $firstApp);
        $this->createGrant($user, $secondApp);

        $loginResponse = $this->login($user);

        $loginResponse->assertOk();

        $cookie = $this->sessionCookie($loginResponse);

        $meResponse = $this->call(
            'GET',
            '/api/iam/me',
            [],
            [SessionService::COOKIE => $cookie],
            [],
            ['HTTP_ACCEPT' => 'application/json'],
        );

        $meResponse->assertOk();

        $loginAppIds = collect($loginResponse->json('access.legacy_apps'))
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $meAppIds = collect($meResponse->json('access.legacy_apps'))
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame($loginAppIds, $meAppIds);

        $this->assertEqualsCanonicalizing(
            [$firstApp, $secondApp],
            $meAppIds,
        );
    }

    public function test_two_users_receive_different_legacy_app_access_lists(): void
    {
        $userA = $this->createUser('effective-user-a');
        $userB = $this->createUser('effective-user-b');

        $appA = $this->createLegacyApp('Application A');
        $appB = $this->createLegacyApp('Application B');

        $this->createGrant($userA, $appA);
        $this->createGrant($userB, $appB);

        $responseA = $this->login($userA);
        $responseB = $this->login($userB);

        $responseA->assertOk()
            ->assertJsonPath('access.legacy_apps.0.id', $appA);

        $responseB->assertOk()
            ->assertJsonPath('access.legacy_apps.0.id', $appB);

        $this->assertCount(1, $responseA->json('access.legacy_apps'));
        $this->assertCount(1, $responseB->json('access.legacy_apps'));
    }

    public function test_user_without_legacy_app_grants_can_still_log_in(): void
    {
        $user = $this->createUser('no-legacy-access-user');

        $response = $this->login($user);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('access.legacy_apps', []);

        $this->assertNotEmpty(
            $this->sessionCookie($response),
        );
    }

    public function test_revoked_grants_are_excluded_from_effective_access(): void
    {
        $user = $this->createUser('revoked-access-user');

        $activeApp = $this->createLegacyApp('Active Grant Application');
        $revokedApp = $this->createLegacyApp('Revoked Grant Application');

        $this->createGrant($user, $activeApp);
        $this->createGrant($user, $revokedApp, now());

        $response = $this->login($user);

        $response->assertOk()
            ->assertJsonPath('access.legacy_apps.0.id', $activeApp);

        $this->assertCount(1, $response->json('access.legacy_apps'));
    }

    public function test_inactive_applications_are_excluded_even_when_granted(): void
    {
        $user = $this->createUser('inactive-app-access-user');

        $activeApp = $this->createLegacyApp('Active Application');
        $inactiveApp = $this->createLegacyApp(
            'Inactive Application',
            false,
        );

        $this->createGrant($user, $activeApp);
        $this->createGrant($user, $inactiveApp);

        $response = $this->login($user);

        $response->assertOk()
            ->assertJsonPath('access.legacy_apps.0.id', $activeApp);

        $this->assertCount(1, $response->json('access.legacy_apps'));
    }

    public function test_legacy_it_admin_flag_does_not_grant_all_legacy_applications(): void
    {
        $user = $this->createUser(
            'legacy-flag-user',
            true,
        );

        $this->createLegacyApp('Unassigned Application');

        $response = $this->login($user);

        $response->assertOk()
            ->assertJsonPath('access.all_access', true)
            ->assertJsonPath('access.legacy_apps', []);
    }

    private function createUser(
        string $username,
        bool $isItAdmin = false,
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
            'is_it_admin' => $isItAdmin,
        ]);
    }

    private function createLegacyApp(
        string $name,
        bool $active = true,
    ): string {
        $id = (string) Str::ulid();

        DB::connection('concord_platform')
            ->table('legacy_apps')
            ->insert([
                'id' => $id,
                'name' => $name,
                'url' => 'https://example.test',
                'is_active' => $active,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return $id;
    }

    private function createGrant(
        User $user,
        string $legacyAppId,
        $revokedAt = null,
    ): UserLegacyAppGrant {
        return UserLegacyAppGrant::query()->create([
            'user_id' => $user->id,
            'legacy_app_id' => $legacyAppId,
            'granted_at' => now(),
            'granted_by' => $user->id,
            'revoked_at' => $revokedAt,
            'revoked_by' => $revokedAt ? $user->id : null,
        ]);
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
