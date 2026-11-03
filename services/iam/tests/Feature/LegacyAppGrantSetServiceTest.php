<?php

namespace Tests\Feature;

use App\Models\LegacyAppGrantSet;
use App\Models\Person;
use App\Models\PlatformAdministrator;
use App\Models\User;
use App\Services\LegacyAppGrantSetService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LegacyAppGrantSetServiceTest extends TestCase
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

    public function test_designated_administrator_can_create_grant_set_with_active_and_inactive_applications(): void
    {
        $administrator = $this->createUser('grant-set-admin');
        $this->createDesignation($administrator);

        $activeAppId = $this->createLegacyApp();
        $inactiveAppId = $this->createLegacyApp(false);

        $grantSet = app(LegacyAppGrantSetService::class)->create([
            'name' => 'General Services',
            'description' => 'General Services applications',
            'legacy_app_ids' => [$activeAppId, $inactiveAppId],
        ], $administrator);

        $this->assertInstanceOf(LegacyAppGrantSet::class, $grantSet);

        $this->assertDatabaseHas('legacy_app_grant_sets', [
            'id' => $grantSet->id,
            'name' => 'General Services',
            'description' => 'General Services applications',
            'deleted_at' => null,
        ]);

        $this->assertDatabaseCount('legacy_app_grant_set_items', 2);

        foreach ([$activeAppId, $inactiveAppId] as $legacyAppId) {
            $this->assertDatabaseHas('legacy_app_grant_set_items', [
                'grant_set_id' => $grantSet->id,
                'legacy_app_id' => $legacyAppId,
            ]);
        }

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'access.legacy_app_grant_set_created',
            'user_id' => $administrator->id,
            'entity_type' => 'legacy_app_grant_set',
            'entity_id' => $grantSet->id,
        ]);
    }

    public function test_non_designated_user_cannot_create_grant_set(): void
    {
        $ordinaryUser = $this->createUser('ordinary-grant-set-user');
        $legacyAppId = $this->createLegacyApp();

        try {
            app(LegacyAppGrantSetService::class)->create([
                'name' => 'Unauthorized Set',
                'legacy_app_ids' => [$legacyAppId],
            ], $ordinaryUser);

            $this->fail('An ordinary user must not create a Grant Set.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('legacy_app_grant_sets', 0);
            $this->assertDatabaseCount('legacy_app_grant_set_items', 0);

            $this->assertDatabaseMissing('audit_logs', [
                'action' => 'access.legacy_app_grant_set_created',
            ]);
        }
    }

    public function test_nonexistent_application_cannot_be_added_to_grant_set(): void
    {
        $administrator = $this->createUser('missing-app-admin');
        $this->createDesignation($administrator);

        try {
            app(LegacyAppGrantSetService::class)->create([
                'name' => 'Invalid Set',
                'legacy_app_ids' => [(string) Str::ulid()],
            ], $administrator);

            $this->fail('A nonexistent application must not be added.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('legacy_app_grant_sets', 0);
            $this->assertDatabaseCount('legacy_app_grant_set_items', 0);
        }
    }

    public function test_designated_administrator_can_update_grant_set_details_and_membership(): void
    {
        $administrator = $this->createUser('update-grant-set-admin');
        $this->createDesignation($administrator);

        $firstAppId = $this->createLegacyApp();
        $secondAppId = $this->createLegacyApp(false);
        $thirdAppId = $this->createLegacyApp();

        $service = app(LegacyAppGrantSetService::class);

        $grantSet = $service->create([
            'name' => 'Original Set',
            'description' => 'Original description',
            'legacy_app_ids' => [$firstAppId, $secondAppId],
        ], $administrator);

        $updatedGrantSet = $service->update($grantSet, [
            'name' => 'Updated Set',
            'description' => 'Updated description',
            'legacy_app_ids' => [$secondAppId, $thirdAppId],
        ], $administrator);

        $this->assertSame('Updated Set', $updatedGrantSet->name);
        $this->assertSame('Updated description', $updatedGrantSet->description);

        $this->assertDatabaseMissing('legacy_app_grant_set_items', [
            'grant_set_id' => $grantSet->id,
            'legacy_app_id' => $firstAppId,
        ]);

        foreach ([$secondAppId, $thirdAppId] as $legacyAppId) {
            $this->assertDatabaseHas('legacy_app_grant_set_items', [
                'grant_set_id' => $grantSet->id,
                'legacy_app_id' => $legacyAppId,
            ]);
        }

        $this->assertDatabaseCount('legacy_app_grant_set_items', 2);

        // Editing a template must never create or modify user grants.
        $this->assertDatabaseCount('user_legacy_app_grants', 0);
        $this->assertDatabaseCount('legacy_app_grant_events', 0);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'access.legacy_app_grant_set_updated',
            'user_id' => $administrator->id,
            'entity_type' => 'legacy_app_grant_set',
            'entity_id' => $grantSet->id,
        ]);
    }

    public function test_grant_set_list_excludes_archived_sets_unless_requested(): void
    {
        $administrator = $this->createUser('list-grant-set-admin');
        $this->createDesignation($administrator);

        $service = app(LegacyAppGrantSetService::class);

        $activeSet = $service->create([
            'name' => 'Active Set',
            'legacy_app_ids' => [],
        ], $administrator);

        $archivedSet = $service->create([
            'name' => 'Archived Set',
            'legacy_app_ids' => [],
        ], $administrator);

        $service->archive($archivedSet, $administrator);

        $activeSets = $service->list($administrator);
        $allSets = $service->list($administrator, true);

        $this->assertTrue(
            $activeSets->contains('id', $activeSet->id),
        );

        $this->assertFalse(
            $activeSets->contains('id', $archivedSet->id),
        );

        $this->assertTrue(
            $allSets->contains('id', $activeSet->id),
        );

        $this->assertTrue(
            $allSets->contains('id', $archivedSet->id),
        );
    }

    public function test_archiving_grant_set_preserves_memberships(): void
    {
        $administrator = $this->createUser('archive-grant-set-admin');
        $this->createDesignation($administrator);

        $legacyAppId = $this->createLegacyApp();

        $service = app(LegacyAppGrantSetService::class);

        $grantSet = $service->create([
            'name' => 'Archive Test Set',
            'legacy_app_ids' => [$legacyAppId],
        ], $administrator);

        $archivedSet = $service->archive($grantSet, $administrator);

        $this->assertTrue($archivedSet->trashed());

        $this->assertSoftDeleted('legacy_app_grant_sets', [
            'id' => $grantSet->id,
        ]);

        $this->assertDatabaseHas('legacy_app_grant_set_items', [
            'grant_set_id' => $grantSet->id,
            'legacy_app_id' => $legacyAppId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'access.legacy_app_grant_set_archived',
            'user_id' => $administrator->id,
            'entity_type' => 'legacy_app_grant_set',
            'entity_id' => $grantSet->id,
        ]);

        $this->assertDatabaseCount('user_legacy_app_grants', 0);
    }

    public function test_designated_administrator_can_restore_archived_grant_set(): void
    {
        $administrator = $this->createUser('restore-grant-set-admin');
        $this->createDesignation($administrator);

        $legacyAppId = $this->createLegacyApp();

        $service = app(LegacyAppGrantSetService::class);

        $grantSet = $service->create([
            'name' => 'Restore Test Set',
            'legacy_app_ids' => [$legacyAppId],
        ], $administrator);

        $service->archive($grantSet, $administrator);

        $restoredSet = $service->restore(
            $grantSet->id,
            $administrator,
        );

        $this->assertFalse($restoredSet->trashed());

        $this->assertDatabaseHas('legacy_app_grant_sets', [
            'id' => $grantSet->id,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('legacy_app_grant_set_items', [
            'grant_set_id' => $grantSet->id,
            'legacy_app_id' => $legacyAppId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'access.legacy_app_grant_set_restored',
            'user_id' => $administrator->id,
            'entity_type' => 'legacy_app_grant_set',
            'entity_id' => $grantSet->id,
        ]);

        // Restoration must not grant application access.
        $this->assertDatabaseCount('user_legacy_app_grants', 0);
        $this->assertDatabaseCount('legacy_app_grant_events', 0);
    }

    public function test_non_designated_user_cannot_archive_or_restore_grant_set(): void
    {
        $administrator = $this->createUser('archive-owner');
        $ordinaryUser = $this->createUser('unauthorized-grant-set-actor');

        $this->createDesignation($administrator);

        $service = app(LegacyAppGrantSetService::class);

        $grantSet = $service->create([
            'name' => 'Protected Set',
            'legacy_app_ids' => [],
        ], $administrator);

        try {
            $service->archive($grantSet, $ordinaryUser);

            $this->fail('An ordinary user must not archive a Grant Set.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('legacy_app_grant_sets', [
                'id' => $grantSet->id,
                'deleted_at' => null,
            ]);
        }

        $service->archive($grantSet, $administrator);

        try {
            $service->restore($grantSet->id, $ordinaryUser);

            $this->fail('An ordinary user must not restore a Grant Set.');
        } catch (AuthorizationException) {
            $this->assertSoftDeleted('legacy_app_grant_sets', [
                'id' => $grantSet->id,
            ]);
        }
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
            'is_it_admin' => false,
        ]);
    }

    private function createDesignation(User $user): void
    {
        $designation = new PlatformAdministrator();
        $designation->id = 1;
        $designation->user_id = $user->id;
        $designation->save();
    }

    private function createLegacyApp(bool $active = true): string
    {
        $id = (string) Str::ulid();

        DB::connection('concord_platform')
            ->table('legacy_apps')
            ->insert([
                'id' => $id,
                'name' => 'Test Application',
                'url' => 'https://example.test',
                'is_active' => $active,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return $id;
    }
}