<?php

namespace Tests\Feature;

use App\Models\LegacyAppGrantSet;
use App\Models\Person;
use App\Models\PlatformAdministrator;
use App\Models\User;
use App\Services\SessionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class LegacyAppDirectoryManagementTest extends TestCase
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
                $table->string('name', 150);
                $table->string('url', 2048);
                $table->string('icon', 100)->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
    }

    public function test_designated_administrator_can_list_active_and_inactive_applications(): void
    {
        $admin = $this->createUser('directory-list-admin');
        $this->designateAdministrator($admin);
        $cookie = $this->login($admin);

        $activeId = $this->createLegacyApp('Student Portal', true, 2);
        $inactiveId = $this->createLegacyApp('HR Portal', false, 1);

        $response = $this->request(
            'GET',
            '/api/iam/admin/legacy-apps',
            $cookie,
        );

        $response->assertOk()
            ->assertJsonPath('data.0.id', $inactiveId)
            ->assertJsonPath('data.0.is_active', false)
            ->assertJsonPath('data.1.id', $activeId)
            ->assertJsonPath('data.1.is_active', true);
    }

    public function test_designated_administrator_can_register_an_application(): void
    {
        $admin = $this->createUser('directory-create-admin');
        $this->designateAdministrator($admin);
        $cookie = $this->login($admin);

        $name = str_repeat('A', 150);

        $response = $this->request(
            'POST',
            '/api/iam/admin/legacy-apps',
            $cookie,
            [
                'name' => $name,
                'url' => 'https://students.example.test',
                'icon' => 'graduation-cap',
                'description' => 'Student information system.',
                'sort_order' => 5,
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('data.name', $name)
            ->assertJsonPath('data.url', 'https://students.example.test')
            ->assertJsonPath('data.icon', 'graduation-cap')
            ->assertJsonPath('data.description', 'Student information system.')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.sort_order', 5);

        $this->assertDatabaseHas('legacy_apps', [
            'id' => $response->json('data.id'),
            'name' => $name,
            'is_active' => true,
        ], 'concord_platform');
    }

    public function test_designated_administrator_can_retrieve_application_details(): void
    {
        $admin = $this->createUser('directory-show-admin');
        $this->designateAdministrator($admin);
        $cookie = $this->login($admin);

        $appId = $this->createLegacyApp('Library Portal', false);

        $response = $this->request(
            'GET',
            "/api/iam/admin/legacy-apps/{$appId}",
            $cookie,
        );

        $response->assertOk()
            ->assertJsonPath('data.id', $appId)
            ->assertJsonPath('data.name', 'Library Portal')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_designated_administrator_can_update_application_details(): void
    {
        $admin = $this->createUser('directory-update-admin');
        $this->designateAdministrator($admin);
        $cookie = $this->login($admin);

        $appId = $this->createLegacyApp('Old Portal');

        $response = $this->request(
            'PATCH',
            "/api/iam/admin/legacy-apps/{$appId}",
            $cookie,
            [
                'name' => 'Updated Portal',
                'url' => 'https://updated.example.test',
                'icon' => 'monitor',
                'description' => 'Updated description.',
                'sort_order' => 10,
            ],
        );

        $response->assertOk()
            ->assertJsonPath('data.id', $appId)
            ->assertJsonPath('data.name', 'Updated Portal')
            ->assertJsonPath('data.url', 'https://updated.example.test')
            ->assertJsonPath('data.icon', 'monitor')
            ->assertJsonPath('data.description', 'Updated description.')
            ->assertJsonPath('data.sort_order', 10);

        $this->assertDatabaseHas('legacy_apps', [
            'id' => $appId,
            'name' => 'Updated Portal',
        ], 'concord_platform');
    }

    public function test_administrator_can_deactivate_and_reactivate_application_without_losing_relationships(): void
    {
        $admin = $this->createUser('directory-status-admin');
        $target = $this->createUser('directory-status-target');

        $this->designateAdministrator($admin);
        $cookie = $this->login($admin);

        $appId = $this->createLegacyApp('Preserved Portal');

        // Create an existing individual grant.
        $grantId = (string) Str::ulid();

        DB::table('user_legacy_app_grants')->insert([
            'id' => $grantId,
            'user_id' => $target->id,
            'legacy_app_id' => $appId,
            'granted_at' => now(),
            'granted_by' => $admin->id,
            'revoked_at' => null,
            'revoked_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create an existing Grant Set membership.
        $grantSet = LegacyAppGrantSet::query()->create([
            'name' => 'Preserved Grant Set',
            'description' => 'Membership preservation test.',
        ]);

        $itemId = (string) Str::ulid();

        DB::table('legacy_app_grant_set_items')->insert([
            'id' => $itemId,
            'grant_set_id' => $grantSet->id,
            'legacy_app_id' => $appId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Deactivate.
        $deactivate = $this->request(
            'POST',
            "/api/iam/admin/legacy-apps/{$appId}/deactivate",
            $cookie,
        );

        $deactivate->assertOk()
            ->assertJsonPath('data.id', $appId)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('legacy_apps', [
            'id' => $appId,
            'is_active' => false,
        ], 'concord_platform');

        // The public directory must exclude inactive applications.
        $publicAfterDeactivation = $this->request(
            'GET',
            '/api/iam/legacy-apps',
            $cookie,
        );

        $publicAfterDeactivation->assertOk()
            ->assertJsonMissing(['id' => $appId]);

        // Both existing relationships must remain intact.
        $this->assertDatabaseHas('user_legacy_app_grants', [
            'id' => $grantId,
            'legacy_app_id' => $appId,
            'revoked_at' => null,
        ]);

        $this->assertDatabaseHas('legacy_app_grant_set_items', [
            'id' => $itemId,
            'grant_set_id' => $grantSet->id,
            'legacy_app_id' => $appId,
        ]);

        // Reactivate the same application.
        $activate = $this->request(
            'POST',
            "/api/iam/admin/legacy-apps/{$appId}/activate",
            $cookie,
        );

        $activate->assertOk()
            ->assertJsonPath('data.id', $appId)
            ->assertJsonPath('data.is_active', true);

        // It should now be available through the public directory again.
        $publicAfterActivation = $this->request(
            'GET',
            '/api/iam/legacy-apps',
            $cookie,
        );

        $publicAfterActivation->assertOk()
            ->assertJsonFragment([
                'id' => $appId,
                'name' => 'Preserved Portal',
            ]);

        // Reactivation must not recreate or duplicate relationships.
        $this->assertDatabaseCount('user_legacy_app_grants', 1);
        $this->assertDatabaseCount('legacy_app_grant_set_items', 1);

        $this->assertDatabaseHas('user_legacy_app_grants', [
            'id' => $grantId,
            'legacy_app_id' => $appId,
            'revoked_at' => null,
        ]);

        $this->assertDatabaseHas('legacy_app_grant_set_items', [
            'id' => $itemId,
            'grant_set_id' => $grantSet->id,
            'legacy_app_id' => $appId,
        ]);
    }

    public function test_invalid_application_data_is_rejected(): void
    {
        $admin = $this->createUser('directory-validation-admin');
        $this->designateAdministrator($admin);
        $cookie = $this->login($admin);

        $response = $this->request(
            'POST',
            '/api/iam/admin/legacy-apps',
            $cookie,
            [
                'name' => '',
                'url' => 'not-a-valid-url',
                'icon' => ['invalid'],
                'description' => ['invalid'],
                'sort_order' => 'not-an-integer',
            ],
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'url',
                'icon',
                'description',
                'sort_order',
            ]);
    }

    public function test_duplicate_application_name_is_rejected(): void
    {
        $admin = $this->createUser('directory-duplicate-admin');
        $this->designateAdministrator($admin);
        $cookie = $this->login($admin);

        $this->createLegacyApp('Student Portal');

        $response = $this->request(
            'POST',
            '/api/iam/admin/legacy-apps',
            $cookie,
            [
                'name' => 'Student Portal',
                'url' => 'https://another.example.test',
            ],
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseCount('legacy_apps', 1, 'concord_platform');
    }

    public function test_duplicate_application_name_is_rejected_when_updating(): void
    {
        $admin = $this->createUser('directory-update-duplicate-admin');
        $this->designateAdministrator($admin);
        $cookie = $this->login($admin);

        $this->createLegacyApp('Student Portal');
        $secondAppId = $this->createLegacyApp('HR Portal');

        $response = $this->request(
            'PATCH',
            "/api/iam/admin/legacy-apps/{$secondAppId}",
            $cookie,
            [
                'name' => 'Student Portal',
            ],
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_ordinary_user_cannot_manage_legacy_app_directory(): void
    {
        $ordinaryUser = $this->createUser('directory-ordinary-user');
        $cookie = $this->login($ordinaryUser);

        $this->request(
            'GET',
            '/api/iam/admin/legacy-apps',
            $cookie,
        )->assertForbidden();

        $this->request(
            'POST',
            '/api/iam/admin/legacy-apps',
            $cookie,
            [
                'name' => 'Unauthorized Portal',
                'url' => 'https://unauthorized.example.test',
            ],
        )->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_manage_legacy_app_directory(): void
    {
        $this->getJson('/api/iam/admin/legacy-apps')
            ->assertUnauthorized();

        $this->postJson('/api/iam/admin/legacy-apps', [
            'name' => 'Unauthorized Portal',
            'url' => 'https://unauthorized.example.test',
        ])->assertUnauthorized();
    }

    public function test_missing_application_returns_not_found(): void
    {
        $admin = $this->createUser('directory-missing-admin');
        $this->designateAdministrator($admin);
        $cookie = $this->login($admin);

        $missingId = (string) Str::ulid();

        $this->request(
            'GET',
            "/api/iam/admin/legacy-apps/{$missingId}",
            $cookie,
        )->assertNotFound();
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
        int $sortOrder = 0,
    ): string {
        $id = (string) Str::ulid();

        DB::connection('concord_platform')
            ->table('legacy_apps')
            ->insert([
                'id' => $id,
                'name' => $name,
                'url' => 'https://example.test',
                'icon' => null,
                'description' => null,
                'is_active' => $active,
                'sort_order' => $sortOrder,
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
