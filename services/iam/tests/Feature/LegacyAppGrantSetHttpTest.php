<?php

namespace Tests\Feature;

use App\Models\LegacyAppGrantEvent;
use App\Models\LegacyAppGrantSetApplicationEvent;
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
use App\Models\LegacyAppGrantSet;

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

    public function test_designated_administrator_can_apply_grant_set_to_a_user(): void
    {
        $admin = $this->createUser('grant-set-apply-admin');
        $target = $this->createUser('grant-set-apply-target');

        $this->designateAdministrator($admin);

        $cookie = $this->login($admin);
        $appId = $this->createLegacyApp('Student Portal');

        $create = $this->request(
            'POST',
            '/api/iam/admin/legacy-apps/grant-sets',
            $cookie,
            [
                'name' => 'Student Access',
                'description' => 'Student applications',
                'legacy_app_ids' => [$appId],
            ],
        );

        $create->assertCreated();

        $grantSetId = $create->json('data.id');

        $response = $this->request(
            'POST',
            "/api/iam/admin/users/{$target->id}/legacy-apps/grant-sets/{$grantSetId}/apply",
            $cookie,
        );

        $response->assertCreated()
            ->assertJsonPath('data.grant_set_id', $grantSetId)
            ->assertJsonPath('data.user_id', $target->id)
            ->assertJsonPath('data.actor_id', $admin->id)
            ->assertJsonPath('data.legacy_app_ids', [$appId]);

        $this->assertDatabaseHas('user_legacy_app_grants', [
            'user_id' => $target->id,
            'legacy_app_id' => $appId,
            'revoked_at' => null,
        ]);

        $this->assertDatabaseHas('legacy_app_grant_events', [
            'event_type' => LegacyAppGrantEvent::EVENT_GRANTED,
            'source' => LegacyAppGrantEvent::SOURCE_GRANT_SET,
        ]);

        $this->assertDatabaseHas('legacy_app_grant_set_application_events', [
            'grant_set_id' => $grantSetId,
            'user_id' => $target->id,
            'actor_id' => $admin->id,
        ]);

        $this->assertSame(
            1,
            LegacyAppGrantSetApplicationEvent::query()
                ->where('grant_set_id', $grantSetId)
                ->where('user_id', $target->id)
                ->count(),
        );

        $this->assertSame(
            1,
            UserLegacyAppGrant::query()
                ->where('user_id', $target->id)
                ->where('legacy_app_id', $appId)
                ->whereNull('revoked_at')
                ->count(),
        );
    }

    public function test_ordinary_user_cannot_apply_grant_set(): void
    {
        $admin = $this->createUser('grant-set-apply-admin');
        $ordinaryUser = $this->createUser('grant-set-apply-ordinary');
        $target = $this->createUser('grant-set-apply-target');

        $this->designateAdministrator($admin);

        $adminCookie = $this->login($admin);
        $ordinaryCookie = $this->login($ordinaryUser);

        $appId = $this->createLegacyApp('Student Portal');

        $create = $this->request(
            'POST',
            '/api/iam/admin/legacy-apps/grant-sets',
            $adminCookie,
            [
                'name' => 'Student Access',
                'description' => null,
                'legacy_app_ids' => [$appId],
            ],
        );

        $create->assertCreated();

        $grantSetId = $create->json('data.id');

        $this->request(
            'POST',
            "/api/iam/admin/users/{$target->id}/legacy-apps/grant-sets/{$grantSetId}/apply",
            $ordinaryCookie,
        )->assertForbidden();
    }

    public function test_designated_administrator_can_retrieve_user_legacy_app_access_and_grant_set_history(): void
    {
        $admin = $this->createUser('legacy-access-admin');
        $target = $this->createUser('legacy-access-target');

        $this->designateAdministrator($admin);

        $cookie = $this->login($admin);
        $appId = $this->createLegacyApp('Student Portal');

        $create = $this->request(
            'POST',
            '/api/iam/admin/legacy-apps/grant-sets',
            $cookie,
            [
                'name' => 'Student Access',
                'description' => 'Student applications',
                'legacy_app_ids' => [$appId],
            ],
        );

        $create->assertCreated();

        $grantSetId = $create->json('data.id');

        $apply = $this->request(
            'POST',
            "/api/iam/admin/users/{$target->id}/legacy-apps/grant-sets/{$grantSetId}/apply",
            $cookie,
        );

        $apply->assertCreated();

        $response = $this->request(
            'GET',
            "/api/iam/admin/users/{$target->id}/legacy-apps",
            $cookie,
        );

        $response->assertOk()
            ->assertJsonPath('data.applications.0.id', $appId)
            ->assertJsonPath('data.applications.0.name', 'Student Portal')
            ->assertJsonPath('data.applications.0.grant_source', LegacyAppGrantEvent::SOURCE_GRANT_SET)
            ->assertJsonPath('data.grant_sets.0.grant_set_id', $grantSetId)
            ->assertJsonPath('data.grant_sets.0.name', 'Student Access');

        $this->assertCount(1, $response->json('data.applications'));
        $this->assertCount(1, $response->json('data.grant_sets'));
    }

    public function test_ordinary_user_cannot_retrieve_another_users_legacy_app_access(): void
    {
        $admin = $this->createUser('legacy-access-admin');
        $ordinaryUser = $this->createUser('legacy-access-ordinary');
        $target = $this->createUser('legacy-access-target');

        $this->designateAdministrator($admin);

        $adminCookie = $this->login($admin);
        $ordinaryCookie = $this->login($ordinaryUser);

        $response = $this->request(
            'GET',
            "/api/iam/admin/users/{$target->id}/legacy-apps",
            $ordinaryCookie,
        );

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_retrieve_legacy_app_access(): void
    {
        $target = $this->createUser('legacy-access-target');

        $response = $this->getJson(
            "/api/iam/admin/users/{$target->id}/legacy-apps",
        );

        $response->assertUnauthorized();
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

        DB::connection('concord_platform')
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

    public function test_designated_administrator_can_batch_assign_individual_apps_to_multiple_users(): void
    {
        $admin = $this->createUser('batch-http-admin');
        $userA = $this->createUser('batch-http-user-a');
        $userB = $this->createUser('batch-http-user-b');

        $this->designateAdministrator($admin);
        $cookie = $this->login($admin);

        $appA = $this->createLegacyApp('Student Portal');
        $appB = $this->createLegacyApp('HR Portal');

        $response = $this->request(
            'POST',
            '/api/iam/admin/legacy-apps/batch-assignments',
            $cookie,
            [
                'user_ids' => [$userA->id, $userB->id],
                'method' => 'individual_apps',
                'legacy_app_ids' => [$appA, $appB],
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('data.method', 'individual_apps');

        $this->assertCount(2, $response->json('data.users'));

        $this->assertDatabaseCount('user_legacy_app_grants', 4);

        $this->assertSame(
            4,
            LegacyAppGrantEvent::query()
                ->where('source', LegacyAppGrantEvent::SOURCE_BATCH)
                ->where('event_type', LegacyAppGrantEvent::EVENT_GRANTED)
                ->count(),
        );

        foreach ([$userA, $userB] as $user) {
            foreach ([$appA, $appB] as $appId) {
                $this->assertDatabaseHas('user_legacy_app_grants', [
                    'user_id' => $user->id,
                    'legacy_app_id' => $appId,
                    'revoked_at' => null,
                ]);
            }
        }
    }

    public function test_designated_administrator_can_batch_apply_a_grant_set(): void
    {
        $admin = $this->createUser('batch-grant-set-http-admin');
        $userA = $this->createUser('batch-grant-set-http-a');
        $userB = $this->createUser('batch-grant-set-http-b');

        $this->designateAdministrator($admin);
        $cookie = $this->login($admin);

        $appA = $this->createLegacyApp('Student Portal');
        $appB = $this->createLegacyApp('HR Portal');

        $create = $this->request(
            'POST',
            '/api/iam/admin/legacy-apps/grant-sets',
            $cookie,
            [
                'name' => 'Batch HTTP Grant Set',
                'description' => 'Batch assignment test',
                'legacy_app_ids' => [$appA, $appB],
            ],
        );

        $create->assertCreated();

        $grantSetId = $create->json('data.id');

        $response = $this->request(
            'POST',
            '/api/iam/admin/legacy-apps/batch-assignments',
            $cookie,
            [
                'user_ids' => [$userA->id, $userB->id],
                'method' => 'grant_set',
                'grant_set_id' => $grantSetId,
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('data.method', 'grant_set');

        $this->assertCount(2, $response->json('data.users'));

        $this->assertDatabaseCount(
            'legacy_app_grant_set_application_events',
            2,
        );

        $this->assertDatabaseCount('user_legacy_app_grants', 4);
    }

    public function test_ordinary_user_cannot_batch_assign_legacy_apps(): void
    {
        $admin = $this->createUser('batch-denied-admin');
        $ordinaryUser = $this->createUser('batch-denied-user');
        $target = $this->createUser('batch-denied-target');

        $this->designateAdministrator($admin);

        $ordinaryCookie = $this->login($ordinaryUser);
        $appId = $this->createLegacyApp('Student Portal');

        $response = $this->request(
            'POST',
            '/api/iam/admin/legacy-apps/batch-assignments',
            $ordinaryCookie,
            [
                'user_ids' => [$target->id],
                'method' => 'individual_apps',
                'legacy_app_ids' => [$appId],
            ],
        );

        $response->assertForbidden();

        $this->assertDatabaseCount('user_legacy_app_grants', 0);
    }

    public function test_unauthenticated_user_cannot_batch_assign_legacy_apps(): void
    {
        $target = $this->createUser('batch-unauthenticated-target');
        $appId = $this->createLegacyApp('Student Portal');

        $response = $this->postJson(
            '/api/iam/admin/legacy-apps/batch-assignments',
            [
                'user_ids' => [$target->id],
                'method' => 'individual_apps',
                'legacy_app_ids' => [$appId],
            ],
        );

        $response->assertUnauthorized();

        $this->assertDatabaseCount('user_legacy_app_grants', 0);
    }

    public function test_batch_assignment_rejects_mixed_assignment_methods(): void
    {
        $admin = $this->createUser('batch-validation-admin');
        $target = $this->createUser('batch-validation-target');

        $this->designateAdministrator($admin);

        $cookie = $this->login($admin);
        $appId = $this->createLegacyApp('Student Portal');

        $response = $this->request(
            'POST',
            '/api/iam/admin/legacy-apps/batch-assignments',
            $cookie,
            [
                'user_ids' => [$target->id],
                'method' => 'individual_apps',
                'grant_set_id' => (string) Str::ulid(),
                'legacy_app_ids' => [$appId],
            ],
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['grant_set_id']);

        $this->assertDatabaseCount('user_legacy_app_grants', 0);
    }

    public function test_batch_assignment_http_request_rolls_back_entire_batch_when_audit_fails(): void
    {
        $admin = $this->createUser('batch-http-rollback-admin');
        $userA = $this->createUser('batch-http-rollback-a');
        $userB = $this->createUser('batch-http-rollback-b');

        $this->designateAdministrator($admin);
        $cookie = $this->login($admin);

        $appA = $this->createLegacyApp('Rollback App A');
        $appB = $this->createLegacyApp('Rollback App B');

        $shouldFailAuditInsert = true;

        DB::connection()->beforeExecuting(
            function (string $query) use (&$shouldFailAuditInsert): void {
                $normalizedQuery = strtolower(ltrim($query));

                if (
                    $shouldFailAuditInsert
                    && str_starts_with($normalizedQuery, 'insert')
                    && str_contains($normalizedQuery, 'audit_logs')
                ) {
                    throw new \RuntimeException(
                        'Simulated HTTP batch audit failure.'
                    );
                }
            },
        );

        try {
            $response = $this->request(
                'POST',
                '/api/iam/admin/legacy-apps/batch-assignments',
                $cookie,
                [
                    'user_ids' => [$userA->id, $userB->id],
                    'method' => 'individual_apps',
                    'legacy_app_ids' => [$appA, $appB],
                ],
            );
        } finally {
            $shouldFailAuditInsert = false;
        }

        $response->assertServerError();

        $this->assertDatabaseCount('user_legacy_app_grants', 0);
        $this->assertDatabaseCount('legacy_app_grant_events', 0);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'access.legacy_app_granted',
        ]);
    }
}