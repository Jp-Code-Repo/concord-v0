<?php

namespace Tests\Feature;

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


class LegacyAppAccessListingTest extends TestCase
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

    public function test_administrator_can_list_users_with_effective_and_inactive_assignments(): void
    {
        $admin = $this->createUser('access-list-admin');
        $target = $this->createUser('access-list-target', [
            'first_name' => 'Alex',
            'last_name' => 'Santos',
        ]);

        $this->designateAdministrator($admin);

        $activeApp = $this->createLegacyApp('Student Portal');
        $inactiveApp = $this->createLegacyApp('Old Registrar', false);
        $revokedApp = $this->createLegacyApp('Revoked Portal');

        $this->createGrant($target, $admin, $activeApp);
        $this->createGrant($target, $admin, $inactiveApp);
        $this->createGrant($target, $admin, $revokedApp, true);

        $response = $this->getAccessListing(
            $this->administratorCookie($admin)
        );

        $response->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.1.username', 'access-list-target')
            ->assertJsonPath('data.1.person.display_name', 'Alex Santos')
            ->assertJsonPath('data.1.effective_application_count', 1)
            ->assertJsonPath('data.1.applications.0.id', $activeApp)
            ->assertJsonPath('data.1.applications.0.name', 'Student Portal')
            ->assertJsonPath('data.1.inactive_assignments.0.id', $inactiveApp)
            ->assertJsonPath('data.1.inactive_assignments.0.name', 'Old Registrar')
            ->assertJsonMissing([
                'id' => $revokedApp,
                'name' => 'Revoked Portal',
            ]);
    }

    public function test_administrator_can_search_users_by_username(): void
    {
        $admin = $this->createUser('access-search-admin');
        $this->designateAdministrator($admin);

        $this->createUser('alex.santos');
        $this->createUser('maria.reyes');

        $response = $this->getAccessListing(
            $this->administratorCookie($admin),
            ['search' => 'alex.santos'],
        );

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.username', 'alex.santos');
    }

    public function test_administrator_can_search_users_by_person_name(): void
    {
        $admin = $this->createUser('access-name-admin');
        $this->designateAdministrator($admin);

        $this->createUser('alex.santos', [
            'first_name' => 'Alex',
            'last_name' => 'Santos',
        ]);

        $this->createUser('maria.reyes', [
            'first_name' => 'Maria',
            'last_name' => 'Reyes',
        ]);

        $response = $this->getAccessListing(
            $this->administratorCookie($admin),
            ['search' => 'Santos'],
        );

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.username', 'alex.santos');
    }

    public function test_administrator_can_filter_users_by_status(): void
    {
        $admin = $this->createUser('access-status-admin');
        $this->designateAdministrator($admin);

        $this->createUser('active.user');
        $this->createUser('disabled.user', [
            'status' => User::STATUS_DISABLED,
        ]);
        $this->createUser('deactivated.user', [
            'status' => User::STATUS_DEACTIVATED,
        ]);

        $response = $this->getAccessListing(
            $this->administratorCookie($admin),
            ['status' => User::STATUS_DISABLED],
        );

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.username', 'disabled.user');
    }

    public function test_listing_preserves_pagination(): void
    {
        $admin = $this->createUser('access-pagination-admin');
        $this->designateAdministrator($admin);

        $this->createUser('user.one');
        $this->createUser('user.two');

        $response = $this->getAccessListing(
            $this->administratorCookie($admin),
            ['per_page' => 1, 'page' => 1],
        );

        $response->assertOk()
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    [
                        'id',
                        'person_id',
                        'username',
                        'status',
                        'person',
                        'effective_application_count',
                        'applications',
                        'inactive_assignments',
                    ],
                ],
                'per_page',
                'total',
                'last_page',
            ])
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('total', 3);
    }

    public function test_ordinary_user_cannot_access_the_access_listing(): void
    {
        $ordinaryUser = $this->createUser('access-list-ordinary');
        $cookie = $this->administratorCookie($ordinaryUser);

        $this->getAccessListing($cookie)->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_the_access_listing(): void
    {
        $this->getJson('/api/iam/admin/legacy-apps/access')
            ->assertUnauthorized();
    }

    private function createUser(
        string $username,
        array $attributes = [],
    ): User {
        $person = Person::query()->create([
            'first_name' => $attributes['first_name'] ?? ucfirst($username),
            'last_name' => $attributes['last_name'] ?? 'Test',
        ]);

        return User::query()->create([
            'person_id' => $person->id,
            'username' => $username,
            'password' => 'test-password',
            'status' => $attributes['status'] ?? User::STATUS_ACTIVE,
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

    private function administratorCookie(User $admin): string
    {
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

        return $cookie->getValue();
    }

    private function getAccessListing(
        string $cookie,
        array $query = [],
    ) {
        return $this->call(
            'GET',
            '/api/iam/admin/legacy-apps/access',
            $query,
            [SessionService::COOKIE => $cookie],
            [],
            ['HTTP_ACCEPT' => 'application/json'],
        );
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
        User $target,
        User $actor,
        string $appId,
        bool $revoked = false,
    ): UserLegacyAppGrant {
        return UserLegacyAppGrant::query()->create([
            'user_id' => $target->id,
            'legacy_app_id' => $appId,
            'granted_at' => now(),
            'granted_by' => $actor->id,
            'revoked_at' => $revoked ? now() : null,
            'revoked_by' => $revoked ? $actor->id : null,
        ]);
    }
}
