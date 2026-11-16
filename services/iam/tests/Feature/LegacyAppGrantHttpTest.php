<?php

namespace Tests\Feature;

use App\Models\LegacyAppGrantEvent;
use App\Models\Person;
use App\Models\PlatformAdministrator;
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

class LegacyAppGrantHttpTest extends TestCase
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

    public function test_designated_administrator_can_grant_an_application(): void
    {
        $admin = $this->createUser('grant-http-admin');
        $target = $this->createUser('grant-http-target');

        $this->designateAdministrator($admin);

        $cookie = $this->login($admin);
        $appId = $this->createLegacyApp('Student Portal');

        $response = $this->request(
            'POST',
            "/api/iam/admin/users/{$target->id}/legacy-apps/{$appId}/grant",
            $cookie,
        );

        $response->assertCreated()
            ->assertJsonPath('data.user_id', $target->id)
            ->assertJsonPath('data.legacy_app_id', $appId)
            ->assertJsonPath('data.granted_by', $admin->id)
            ->assertJsonPath('data.revoked_at', null);

        $grantId = $response->json('data.id');

        $this->assertDatabaseHas('user_legacy_app_grants', [
            'id' => $grantId,
            'user_id' => $target->id,
            'legacy_app_id' => $appId,
            'granted_by' => $admin->id,
            'revoked_at' => null,
        ]);

        $this->assertDatabaseHas('legacy_app_grant_events', [
            'grant_id' => $grantId,
            'event_type' => LegacyAppGrantEvent::EVENT_GRANTED,
            'actor_id' => $admin->id,
            'source' => LegacyAppGrantEvent::SOURCE_INDIVIDUAL,
        ]);
    }

    public function test_designated_administrator_can_grant_an_inactive_application(): void
    {
        $admin = $this->createUser('inactive-grant-admin');
        $target = $this->createUser('inactive-grant-target');

        $this->designateAdministrator($admin);

        $cookie = $this->login($admin);
        $appId = $this->createLegacyApp('Inactive Portal', false);

        $response = $this->request(
            'POST',
            "/api/iam/admin/users/{$target->id}/legacy-apps/{$appId}/grant",
            $cookie,
        );

        $response->assertCreated()
            ->assertJsonPath('data.legacy_app_id', $appId);

        $this->assertDatabaseHas('user_legacy_app_grants', [
            'user_id' => $target->id,
            'legacy_app_id' => $appId,
            'revoked_at' => null,
        ]);
    }

    public function test_duplicate_active_grant_is_rejected(): void
    {
        $admin = $this->createUser('duplicate-grant-admin');
        $target = $this->createUser('duplicate-grant-target');

        $this->designateAdministrator($admin);

        $cookie = $this->login($admin);
        $appId = $this->createLegacyApp('Student Portal');

        $first = $this->request(
            'POST',
            "/api/iam/admin/users/{$target->id}/legacy-apps/{$appId}/grant",
            $cookie,
        );

        $first->assertCreated();

        $duplicate = $this->request(
            'POST',
            "/api/iam/admin/users/{$target->id}/legacy-apps/{$appId}/grant",
            $cookie,
        );

        $duplicate->assertUnprocessable()
            ->assertJsonValidationErrors(['legacy_app_id']);

        $this->assertDatabaseCount('user_legacy_app_grants', 1);
        $this->assertDatabaseCount('legacy_app_grant_events', 1);
    }

    public function test_designated_administrator_can_revoke_an_application_grant(): void
    {
        $admin = $this->createUser('revoke-http-admin');
        $target = $this->createUser('revoke-http-target');

        $this->designateAdministrator($admin);

        $cookie = $this->login($admin);
        $appId = $this->createLegacyApp('HR Portal');

        $grantResponse = $this->request(
            'POST',
            "/api/iam/admin/users/{$target->id}/legacy-apps/{$appId}/grant",
            $cookie,
        );

        $grantResponse->assertCreated();
        $grantId = $grantResponse->json('data.id');

        $revokeResponse = $this->request(
            'POST',
            "/api/iam/admin/users/{$target->id}/legacy-apps/{$appId}/revoke",
            $cookie,
        );

        $revokeResponse->assertOk()
            ->assertJsonPath('data.id', $grantId)
            ->assertJsonPath('data.user_id', $target->id)
            ->assertJsonPath('data.legacy_app_id', $appId)
            ->assertJsonPath('data.revoked_by', $admin->id);

        $this->assertNotNull(
            $revokeResponse->json('data.revoked_at')
        );

        $this->assertDatabaseHas('user_legacy_app_grants', [
            'id' => $grantId,
            'revoked_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('legacy_app_grant_events', [
            'grant_id' => $grantId,
            'event_type' => LegacyAppGrantEvent::EVENT_REVOKED,
            'actor_id' => $admin->id,
            'source' => LegacyAppGrantEvent::SOURCE_INDIVIDUAL,
        ]);

        $this->assertDatabaseCount('user_legacy_app_grants', 1);
        $this->assertDatabaseCount('legacy_app_grant_events', 2);
    }

    public function test_already_revoked_grant_cannot_be_revoked_again(): void
    {
        $admin = $this->createUser('repeat-revoke-http-admin');
        $target = $this->createUser('repeat-revoke-http-target');

        $this->designateAdministrator($admin);

        $cookie = $this->login($admin);
        $appId = $this->createLegacyApp('Library Portal');

        $grantResponse = $this->request(
            'POST',
            "/api/iam/admin/users/{$target->id}/legacy-apps/{$appId}/grant",
            $cookie,
        );

        $grantResponse->assertCreated();

        $path = "/api/iam/admin/users/{$target->id}/legacy-apps/{$appId}/revoke";

        $this->request('POST', $path, $cookie)->assertOk();

        $secondRevoke = $this->request('POST', $path, $cookie);

        $secondRevoke->assertUnprocessable()
            ->assertJsonValidationErrors(['grant']);

        $this->assertDatabaseCount('user_legacy_app_grants', 1);
        $this->assertDatabaseCount('legacy_app_grant_events', 2);
    }

    public function test_ordinary_user_cannot_grant_or_revoke_applications(): void
    {
        $admin = $this->createUser('protected-grant-admin');
        $ordinaryUser = $this->createUser('ordinary-grant-actor');
        $target = $this->createUser('protected-grant-target');

        $this->designateAdministrator($admin);

        $adminCookie = $this->login($admin);
        $ordinaryCookie = $this->login($ordinaryUser);
        $appId = $this->createLegacyApp('Protected Portal');

        $this->request(
            'POST',
            "/api/iam/admin/users/{$target->id}/legacy-apps/{$appId}/grant",
            $adminCookie,
        )->assertCreated();

        $this->request(
            'POST',
            "/api/iam/admin/users/{$target->id}/legacy-apps/{$appId}/grant",
            $ordinaryCookie,
        )->assertForbidden();

        $this->request(
            'POST',
            "/api/iam/admin/users/{$target->id}/legacy-apps/{$appId}/revoke",
            $ordinaryCookie,
        )->assertForbidden();

        $this->assertDatabaseCount('user_legacy_app_grants', 1);
        $this->assertDatabaseCount('legacy_app_grant_events', 1);
    }

    public function test_unauthenticated_user_cannot_grant_or_revoke_applications(): void
    {
        $target = $this->createUser('unauthenticated-grant-target');
        $appId = $this->createLegacyApp('Protected Portal');

        $path = "/api/iam/admin/users/{$target->id}/legacy-apps/{$appId}";

        $this->postJson("{$path}/grant")->assertUnauthorized();
        $this->postJson("{$path}/revoke")->assertUnauthorized();

        $this->assertDatabaseCount('user_legacy_app_grants', 0);
        $this->assertDatabaseCount('legacy_app_grant_events', 0);
    }

    public function test_nonexistent_application_cannot_be_granted(): void
    {
        $admin = $this->createUser('missing-app-http-admin');
        $target = $this->createUser('missing-app-http-target');

        $this->designateAdministrator($admin);

        $cookie = $this->login($admin);
        $missingAppId = (string) Str::ulid();

        $response = $this->request(
            'POST',
            "/api/iam/admin/users/{$target->id}/legacy-apps/{$missingAppId}/grant",
            $cookie,
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['legacy_app_id']);

        $this->assertDatabaseCount('user_legacy_app_grants', 0);
        $this->assertDatabaseCount('legacy_app_grant_events', 0);
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

    private function designateAdministrator(User $user): void
    {
        $designation = new PlatformAdministrator();
        $designation->id = 1;
        $designation->user_id = $user->id;
        $designation->save();
    }

    private function login(User $user): string
    {
        $response = $this->postJson('/api/iam/login', [
            'username' => $user->username,
            'password' => 'test-password',
        ]);

        $response->assertOk();

        $cookie = collect($response->headers->getCookies())
            ->first(
                fn (Cookie $cookie) =>
                    $cookie->getName() === SessionService::COOKIE
            );

        $this->assertNotNull($cookie);

        return $cookie->getValue();
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

    private function request(
        string $method,
        string $path,
        ?string $cookie = null,
        array $data = [],
    ) {
        $cookies = $cookie
            ? [SessionService::COOKIE => $cookie]
            : [];

        return $this->call(
            $method,
            $path,
            $data,
            $cookies,
            [],
            ['HTTP_ACCEPT' => 'application/json'],
        );
    }
}
