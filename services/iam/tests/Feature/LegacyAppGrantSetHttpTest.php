<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PlatformAdministrator;
use App\Models\User;
use App\Services\SessionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;

class LegacyAppGrantSetHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_designated_administrator_can_create_and_list_grant_sets(): void
    {
        $admin = $this->createUser('grant-set-admin');
        $this->designateAdministrator($admin);

        $cookie = $this->login($admin);
        $appId = $this->createLegacyApp('Student Portal');

        $create = $this->request(
            'POST',
            '/api/iam/admin/legacy-apps/grant-sets',
            $cookie,
            [
                'name' => 'Teachers',
                'description' => 'Access for teachers',
                'legacy_app_ids' => [$appId],
            ],
        );

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Teachers')
            ->assertJsonPath('data.description', 'Access for teachers');

        $grantSetId = $create->json('data.id');

        $this->assertNotEmpty($grantSetId);

        $list = $this->request(
            'GET',
            '/api/iam/admin/legacy-apps/grant-sets',
            $cookie,
        );

        $list->assertOk()
            ->assertJsonFragment([
                'id' => $grantSetId,
                'name' => 'Teachers',
            ]);
    }

    public function test_designated_administrator_can_view_and_update_a_grant_set(): void
    {
        $admin = $this->createUser('grant-set-editor');
        $this->designateAdministrator($admin);

        $cookie = $this->login($admin);
        $firstAppId = $this->createLegacyApp('Student Portal');
        $secondAppId = $this->createLegacyApp('HR Portal');

        $create = $this->request(
            'POST',
            '/api/iam/admin/legacy-apps/grant-sets',
            $cookie,
            [
                'name' => 'Original Set',
                'description' => 'Original description',
                'legacy_app_ids' => [$firstAppId],
            ],
        );

        $create->assertCreated();

        $grantSetId = $create->json('data.id');

        $show = $this->request(
            'GET',
            "/api/iam/admin/legacy-apps/grant-sets/{$grantSetId}",
            $cookie,
        );

        $show->assertOk()
            ->assertJsonPath('data.id', $grantSetId)
            ->assertJsonPath('data.name', 'Original Set');

        $update = $this->request(
            'PATCH',
            "/api/iam/admin/legacy-apps/grant-sets/{$grantSetId}",
            $cookie,
            [
                'name' => 'Updated Set',
                'description' => 'Updated description',
                'legacy_app_ids' => [$secondAppId],
            ],
        );

        $update->assertOk()
            ->assertJsonPath('data.name', 'Updated Set')
            ->assertJsonPath('data.description', 'Updated description');
    }

    public function test_designated_administrator_can_archive_and_restore_a_grant_set(): void
    {
        $admin = $this->createUser('grant-set-archiver');
        $this->designateAdministrator($admin);

        $cookie = $this->login($admin);
        $appId = $this->createLegacyApp('Library Portal');

        $create = $this->request(
            'POST',
            '/api/iam/admin/legacy-apps/grant-sets',
            $cookie,
            [
                'name' => 'Library Staff',
                'description' => null,
                'legacy_app_ids' => [$appId],
            ],
        );

        $create->assertCreated();

        $grantSetId = $create->json('data.id');

        $archive = $this->request(
            'POST',
            "/api/iam/admin/legacy-apps/grant-sets/{$grantSetId}/archive",
            $cookie,
        );

        $archive->assertOk()
            ->assertJsonPath('message', 'Grant Set archived.');

        $activeList = $this->request(
            'GET',
            '/api/iam/admin/legacy-apps/grant-sets',
            $cookie,
        );

        $activeList->assertOk()
            ->assertJsonMissing(['id' => $grantSetId]);

        $archivedList = $this->request(
            'GET',
            '/api/iam/admin/legacy-apps/grant-sets?include_archived=1',
            $cookie,
        );

        $archivedList->assertOk()
            ->assertJsonFragment([
                'id' => $grantSetId,
                'name' => 'Library Staff',
            ]);

            // Archived details are hidden by default.
            $this->request(
                'GET',
                "/api/iam/admin/legacy-apps/grant-sets/{$grantSetId}",
                $cookie,
            )->assertNotFound();

            // Administrator can retrieve archived details explicitly.
            $this->request(
                'GET',
                "/api/iam/admin/legacy-apps/grant-sets/{$grantSetId}?include_archived=1",
                $cookie,
            )->assertOk()
                ->assertJsonPath('data.id', $grantSetId)
                ->assertJsonPath('data.name', 'Library Staff');

        $restore = $this->request(
            'POST',
            "/api/iam/admin/legacy-apps/grant-sets/{$grantSetId}/restore",
            $cookie,
        );

        $restore->assertOk()
            ->assertJsonPath('data.id', $grantSetId);
    }

    public function test_ordinary_user_cannot_access_grant_set_endpoints(): void
    {
        $ordinaryUser = $this->createUser('ordinary-user');
        $cookie = $this->login($ordinaryUser);

        $response = $this->request(
            'GET',
            '/api/iam/admin/legacy-apps/grant-sets',
            $cookie,
        );

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_grant_set_endpoints(): void
    {
        $response = $this->getJson(
            '/api/iam/admin/legacy-apps/grant-sets',
        );

        $response->assertUnauthorized();
    }

    public function test_invalid_grant_set_data_is_rejected(): void
    {
        $admin = $this->createUser('grant-set-validator');
        $this->designateAdministrator($admin);

        $cookie = $this->login($admin);

        $response = $this->request(
            'POST',
            '/api/iam/admin/legacy-apps/grant-sets',
            $cookie,
            [
                'name' => '',
                'description' => null,
                'legacy_app_ids' => ['not-a-valid-ulid'],
            ],
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'legacy_app_ids.0',
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

        // This test class does not test login rate limiting.
        $this->withoutMiddleware(ThrottleRequests::class);
        
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

    private function createLegacyApp(string $name): string
    {
        $id = (string) Str::ulid();

        \Illuminate\Support\Facades\DB::connection('concord_platform')
            ->table('legacy_apps')
            ->insert([
                'id' => $id,
                'name' => $name,
                'url' => 'https://example.test',
                'is_active' => true,
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
