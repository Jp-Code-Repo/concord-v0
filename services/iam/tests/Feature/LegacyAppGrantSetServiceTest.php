<?php

namespace Tests\Feature;

use App\Models\LegacyAppGrantSet;
use App\Models\Person;
use App\Models\PlatformAdministrator;
use App\Models\User;
use App\Services\LegacyAppGrantSetService;
use Illuminate\Auth\Access\AuthorizationException;
//use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\LegacyAppGrantEvent;
use App\Models\LegacyAppGrantSetApplicationEvent;
use App\Models\UserLegacyAppGrant;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LegacyAppGrantSetServiceTest extends TestCase
{
    //use DatabaseMigrations;
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

    public function test_designated_administrator_can_apply_grant_set_with_inactive_applications_skipped(): void
    {
        $administrator = $this->createUser('apply-grant-set-admin');
        $target = $this->createUser('apply-grant-set-target');

        $this->createDesignation($administrator);

        $activeAppId = $this->createLegacyApp();
        $inactiveAppId = $this->createLegacyApp(false);

        $service = app(LegacyAppGrantSetService::class);

        $grantSet = $service->create([
            'name' => 'Application Test Set',
            'legacy_app_ids' => [$activeAppId, $inactiveAppId],
        ], $administrator);

        $applicationEvent = $service->apply(
            $grantSet,
            $target,
            $administrator,
        );

        $this->assertInstanceOf(
            LegacyAppGrantSetApplicationEvent::class,
            $applicationEvent,
        );

        $this->assertSame($target->id, $applicationEvent->user_id);
        $this->assertSame($administrator->id, $applicationEvent->actor_id);

        $this->assertEqualsCanonicalizing(
            [$activeAppId, $inactiveAppId],
            $applicationEvent->legacy_app_ids,
        );

        // Only the active application receives a grant.
        $this->assertDatabaseCount('user_legacy_app_grants', 1);

        $grant = UserLegacyAppGrant::query()
            ->where('user_id', $target->id)
            ->where('legacy_app_id', $activeAppId)
            ->firstOrFail();

        $this->assertTrue($grant->isActive());

        // The grant event identifies the Grant Set as its source.
        $this->assertDatabaseHas('legacy_app_grant_events', [
            'grant_id' => $grant->id,
            'event_type' => LegacyAppGrantEvent::EVENT_GRANTED,
            'source' => LegacyAppGrantEvent::SOURCE_GRANT_SET,
        ]);

        $this->assertDatabaseCount('legacy_app_grant_events', 1);
        $this->assertDatabaseCount(
            'legacy_app_grant_set_application_events',
            1,
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'access.legacy_app_grant_set_applied',
            'user_id' => $administrator->id,
            'entity_type' => 'legacy_app_grant_set',
            'entity_id' => $grantSet->id,
        ]);
    }

    public function test_reapplying_grant_set_does_not_duplicate_active_grants(): void
    {
        $administrator = $this->createUser('reapply-grant-set-admin');
        $target = $this->createUser('reapply-grant-set-target');

        $this->createDesignation($administrator);

        $activeAppId = $this->createLegacyApp();

        $service = app(LegacyAppGrantSetService::class);

        $grantSet = $service->create([
            'name' => 'Reapplication Test Set',
            'legacy_app_ids' => [$activeAppId],
        ], $administrator);

        $firstApplication = $service->apply(
            $grantSet,
            $target,
            $administrator,
        );

        $originalGrant = UserLegacyAppGrant::query()
            ->where('user_id', $target->id)
            ->where('legacy_app_id', $activeAppId)
            ->firstOrFail();

        $originalGrantedAt = $originalGrant->granted_at;

        $secondApplication = $service->apply(
            $grantSet,
            $target,
            $administrator,
        );

        $originalGrant->refresh();

        // Reapplication must preserve the existing grant period.
        $this->assertSame($originalGrant->id, $originalGrant->fresh()->id);
        $this->assertEquals($originalGrantedAt, $originalGrant->granted_at);

        // No duplicate active grant or grant event is created.
        $this->assertDatabaseCount('user_legacy_app_grants', 1);
        $this->assertDatabaseCount('legacy_app_grant_events', 1);

        // Each successful application has its own history event.
        $this->assertNotSame($firstApplication->id, $secondApplication->id);

        $this->assertDatabaseCount(
            'legacy_app_grant_set_application_events',
            2,
        );
    }

    public function test_reapplying_grant_set_after_revocation_creates_a_new_grant_period(): void
    {
        $administrator = $this->createUser('regrant-set-admin');
        $target = $this->createUser('regrant-set-target');

        $this->createDesignation($administrator);

        $activeAppId = $this->createLegacyApp();

        $grantSetService = app(LegacyAppGrantSetService::class);
        $grantService = app(\App\Services\LegacyAppGrantService::class);

        $grantSet = $grantSetService->create([
            'name' => 'Regrant Test Set',
            'legacy_app_ids' => [$activeAppId],
        ], $administrator);

        $grantSetService->apply($grantSet, $target, $administrator);

        $originalGrant = UserLegacyAppGrant::query()
            ->where('user_id', $target->id)
            ->where('legacy_app_id', $activeAppId)
            ->firstOrFail();

        $grantService->revoke($originalGrant, $administrator);

        $this->assertDatabaseHas('user_legacy_app_grants', [
            'id' => $originalGrant->id,
            'revoked_by' => $administrator->id,
        ]);

        $secondApplication = $grantSetService->apply(
            $grantSet,
            $target,
            $administrator,
        );

        $grantPeriods = UserLegacyAppGrant::query()
            ->where('user_id', $target->id)
            ->where('legacy_app_id', $activeAppId)
            ->orderBy('granted_at')
            ->get();

        $this->assertCount(2, $grantPeriods);

        $this->assertFalse($grantPeriods[0]->isActive());
        $this->assertTrue($grantPeriods[1]->isActive());

        $this->assertNotSame(
            $grantPeriods[0]->id,
            $grantPeriods[1]->id,
        );

        $this->assertDatabaseCount('legacy_app_grant_events', 3);
        $this->assertDatabaseCount(
            'legacy_app_grant_set_application_events',
            2,
        );

        $this->assertDatabaseHas('legacy_app_grant_events', [
            'grant_id' => $grantPeriods[1]->id,
            'event_type' => LegacyAppGrantEvent::EVENT_GRANTED,
            'source' => LegacyAppGrantEvent::SOURCE_GRANT_SET,
        ]);

        $this->assertDatabaseHas('legacy_app_grant_set_application_events', [
            'id' => $secondApplication->id,
            'grant_set_id' => $grantSet->id,
            'user_id' => $target->id,
        ]);
    }

    public function test_non_designated_user_cannot_apply_grant_set(): void
    {
        $administrator = $this->createUser('apply-owner-admin');
        $ordinaryUser = $this->createUser('apply-unauthorized-user');
        $target = $this->createUser('apply-unauthorized-target');

        $this->createDesignation($administrator);

        $activeAppId = $this->createLegacyApp();

        $service = app(LegacyAppGrantSetService::class);

        $grantSet = $service->create([
            'name' => 'Protected Application Set',
            'legacy_app_ids' => [$activeAppId],
        ], $administrator);

        try {
            $service->apply($grantSet, $target, $ordinaryUser);

            $this->fail('A non-designated user must not apply a Grant Set.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount(
                'legacy_app_grant_set_application_events',
                0,
            );

            $this->assertDatabaseCount('user_legacy_app_grants', 0);
            $this->assertDatabaseCount('legacy_app_grant_events', 0);
        }
    }

    public function test_archived_grant_set_cannot_be_applied(): void
    {
        $administrator = $this->createUser('archived-apply-admin');
        $target = $this->createUser('archived-apply-target');

        $this->createDesignation($administrator);

        $activeAppId = $this->createLegacyApp();

        $service = app(LegacyAppGrantSetService::class);

        $grantSet = $service->create([
            'name' => 'Archived Application Set',
            'legacy_app_ids' => [$activeAppId],
        ], $administrator);

        $service->archive($grantSet, $administrator);

        try {
            $service->apply($grantSet, $target, $administrator);

            $this->fail('An archived Grant Set must not be applied.');
        } catch (ValidationException) {
            $this->assertDatabaseCount(
                'legacy_app_grant_set_application_events',
                0,
            );

            $this->assertDatabaseCount('user_legacy_app_grants', 0);
            $this->assertDatabaseCount('legacy_app_grant_events', 0);
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

    public function test_applying_grant_set_invalidates_users_access_cache(): void
    {
        $administrator = $this->createUser('cache-grant-set-admin');
        $target = $this->createUser('cache-grant-set-target');

        $this->createDesignation($administrator);

        $legacyAppId = $this->createLegacyApp();

        $service = app(LegacyAppGrantSetService::class);

        $grantSet = $service->create([
            'name' => 'Cache Invalidation Set',
            'legacy_app_ids' => [$legacyAppId],
        ], $administrator);

        Cache::shouldReceive('forget')
            ->once()
            ->with("access:{$target->id}")
            ->andReturn(true);

        Cache::shouldReceive('forget')
            ->once()
            ->with("access:{$target->id}:it-admin")
            ->andReturn(true);

        $service->apply($grantSet, $target, $administrator);
    }
}
