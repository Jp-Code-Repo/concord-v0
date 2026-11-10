<?php

namespace Tests\Feature;

use App\Models\LegacyAppGrantEvent;
use App\Models\Person;
use App\Models\PlatformAdministrator;
use App\Models\User;
use App\Models\UserLegacyAppGrant;
use App\Services\LegacyAppGrantService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class LegacyAppGrantServiceTest extends TestCase
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

    public function test_designated_administrator_can_grant_an_active_application(): void
    {
        $administrator = $this->createUser('designated-admin');
        $target = $this->createUser('grant-target');

        $this->createDesignation($administrator);

        $legacyAppId = $this->createLegacyApp();

        $grant = app(LegacyAppGrantService::class)->grant(
            $target,
            $legacyAppId,
            $administrator,
            ['reason' => 'Authorized access'],
        );

        $this->assertInstanceOf(UserLegacyAppGrant::class, $grant);
        $this->assertTrue($grant->isActive());

        $this->assertDatabaseHas('user_legacy_app_grants', [
            'id' => $grant->id,
            'user_id' => $target->id,
            'legacy_app_id' => $legacyAppId,
            'granted_by' => $administrator->id,
            'revoked_at' => null,
        ]);

        $this->assertDatabaseHas('legacy_app_grant_events', [
            'grant_id' => $grant->id,
            'event_type' => LegacyAppGrantEvent::EVENT_GRANTED,
            'actor_id' => $administrator->id,
            'source' => LegacyAppGrantEvent::SOURCE_INDIVIDUAL,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'access.legacy_app_granted',
            'user_id' => $administrator->id,
            'entity_type' => 'legacy_app_grant',
            'entity_id' => $grant->id,
        ]);
    }

    public function test_non_designated_user_cannot_grant_an_application(): void
    {
        $ordinaryUser = $this->createUser('ordinary-actor');
        $target = $this->createUser('unauthorized-target');

        $legacyAppId = $this->createLegacyApp();

        try {
            app(LegacyAppGrantService::class)->grant(
                $target,
                $legacyAppId,
                $ordinaryUser,
            );

            $this->fail('An ordinary user must not be allowed to grant access.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('user_legacy_app_grants', 0);
            $this->assertDatabaseCount('legacy_app_grant_events', 0);

            $this->assertDatabaseMissing('audit_logs', [
                'action' => 'access.legacy_app_granted',
            ]);
        }
    }

    public function test_designated_administrator_can_grant_an_inactive_application(): void
    {
        $administrator = $this->createUser('inactive-app-admin');
        $target = $this->createUser('inactive-app-target');

        $this->createDesignation($administrator);

        $legacyAppId = $this->createLegacyApp(false);

        $grant = app(LegacyAppGrantService::class)->grant(
            $target,
            $legacyAppId,
            $administrator,
        );

        $this->assertInstanceOf(UserLegacyAppGrant::class, $grant);
        $this->assertTrue($grant->isActive());

        $this->assertDatabaseHas('user_legacy_app_grants', [
            'id' => $grant->id,
            'user_id' => $target->id,
            'legacy_app_id' => $legacyAppId,
            'granted_by' => $administrator->id,
            'revoked_at' => null,
        ]);

        $this->assertDatabaseHas('legacy_app_grant_events', [
            'grant_id' => $grant->id,
            'event_type' => LegacyAppGrantEvent::EVENT_GRANTED,
            'actor_id' => $administrator->id,
            'source' => LegacyAppGrantEvent::SOURCE_INDIVIDUAL,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'access.legacy_app_granted',
            'user_id' => $administrator->id,
            'entity_type' => 'legacy_app_grant',
            'entity_id' => $grant->id,
        ]);
    }

    public function test_nonexistent_application_cannot_be_granted(): void
    {
        $administrator = $this->createUser('missing-app-admin');
        $target = $this->createUser('missing-app-target');

        $this->createDesignation($administrator);

        try {
            app(LegacyAppGrantService::class)->grant(
                $target,
                (string) Str::ulid(),
                $administrator,
            );

            $this->fail('A nonexistent application must not be granted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('user_legacy_app_grants', 0);
            $this->assertDatabaseCount('legacy_app_grant_events', 0);
        }
    }

    public function test_designated_administrator_can_revoke_a_grant_for_an_inactive_application(): void
    {
        $administrator = $this->createUser('revoke-admin');
        $target = $this->createUser('revoke-target');

        $this->createDesignation($administrator);

        $legacyAppId = $this->createLegacyApp();

        $service = app(LegacyAppGrantService::class);

        $grant = $service->grant(
            $target,
            $legacyAppId,
            $administrator,
        );

        DB::connection('concord_platform')
            ->table('legacy_apps')
            ->where('id', $legacyAppId)
            ->update(['is_active' => false]);

        $revokedGrant = $service->revoke(
            $grant,
            $administrator,
            ['reason' => 'Application access withdrawn'],
        );

        $this->assertFalse($revokedGrant->isActive());
        $this->assertSame($administrator->id, $revokedGrant->revoked_by);

        $this->assertDatabaseHas('legacy_app_grant_events', [
            'grant_id' => $grant->id,
            'event_type' => LegacyAppGrantEvent::EVENT_REVOKED,
            'actor_id' => $administrator->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'access.legacy_app_revoked',
            'user_id' => $administrator->id,
            'entity_type' => 'legacy_app_grant',
            'entity_id' => $grant->id,
        ]);

        $this->assertDatabaseCount('user_legacy_app_grants', 1);
        $this->assertDatabaseCount('legacy_app_grant_events', 2);
    }

    public function test_non_designated_user_cannot_revoke_a_grant(): void
    {
        $administrator = $this->createUser('revoke-owner');
        $ordinaryUser = $this->createUser('unauthorized-revoker');
        $target = $this->createUser('revoke-protected-target');

        $this->createDesignation($administrator);

        $grant = UserLegacyAppGrant::query()->create([
            'user_id' => $target->id,
            'legacy_app_id' => (string) Str::ulid(),
            'granted_at' => now(),
            'granted_by' => $administrator->id,
        ]);

        try {
            app(LegacyAppGrantService::class)->revoke(
                $grant,
                $ordinaryUser,
            );

            $this->fail('An ordinary user must not be allowed to revoke access.');
        } catch (AuthorizationException) {
            $this->assertTrue($grant->fresh()->isActive());

            $this->assertDatabaseCount('legacy_app_grant_events', 0);

            $this->assertDatabaseMissing('audit_logs', [
                'action' => 'access.legacy_app_revoked',
            ]);
        }
    }

    public function test_already_revoked_grant_cannot_be_revoked_again(): void
    {
        $administrator = $this->createUser('repeat-revoke-admin');
        $target = $this->createUser('repeat-revoke-target');

        $this->createDesignation($administrator);

        $grant = UserLegacyAppGrant::query()->create([
            'user_id' => $target->id,
            'legacy_app_id' => (string) Str::ulid(),
            'granted_at' => now(),
            'granted_by' => $administrator->id,
        ]);

        $grant->update([
            'revoked_at' => now(),
            'revoked_by' => $administrator->id,
        ]);

        try {
            app(LegacyAppGrantService::class)->revoke(
                $grant,
                $administrator,
            );

            $this->fail('An already revoked grant must not be revoked again.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('legacy_app_grant_events', 0);

            $this->assertDatabaseMissing('audit_logs', [
                'action' => 'access.legacy_app_revoked',
            ]);
        }
    }

    public function test_designated_administrator_can_read_users_current_application_access(): void
    {
        $administrator = $this->createUser('access-read-admin');
        $target = $this->createUser('access-read-target');

        $this->createDesignation($administrator);

        $activeAppId = $this->createLegacyApp();
        $inactiveAppId = $this->createLegacyApp(false);

        $service = app(LegacyAppGrantService::class);

        $service->grant($target, $activeAppId, $administrator);
        $service->grant($target, $inactiveAppId, $administrator);

        $result = $service->getUserAccess($target, $administrator);

        $this->assertCount(1, $result['applications']);
        $this->assertSame($activeAppId, $result['applications'][0]['id']);
        $this->assertSame(
            LegacyAppGrantEvent::SOURCE_INDIVIDUAL,
            $result['applications'][0]['grant_source'],
        );
        $this->assertCount(0, $result['grant_sets']);
    }

    public function test_grant_transaction_rolls_back_when_audit_insert_fails(): void
    {
        $administrator = $this->createUser('audit-failure-grant-admin');
        $target = $this->createUser('audit-failure-grant-target');

        $this->createDesignation($administrator);
        $legacyAppId = $this->createLegacyApp();

        $shouldFailAuditInsert = true;

        DB::connection()->beforeExecuting(
            function (string $query) use (&$shouldFailAuditInsert): void {
                $normalizedQuery = strtolower(ltrim($query));

                if (
                    $shouldFailAuditInsert
                    && str_starts_with($normalizedQuery, 'insert')
                    && str_contains($normalizedQuery, 'audit_logs')
                ) {
                    throw new RuntimeException('Simulated audit insert failure.');
                }
            },
        );

        try {
            try {
                app(LegacyAppGrantService::class)->grant(
                    $target,
                    $legacyAppId,
                    $administrator,
                );

                $this->fail('The audit insert failure should abort the grant transaction.');
            } catch (RuntimeException $exception) {
                $this->assertSame(
                    'Simulated audit insert failure.',
                    $exception->getMessage(),
                );
            }
        } finally {
            $shouldFailAuditInsert = false;
        }

        $this->assertDatabaseCount('user_legacy_app_grants', 0);
        $this->assertDatabaseCount('legacy_app_grant_events', 0);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'access.legacy_app_granted',
        ]);
    }

    public function test_revoke_transaction_rolls_back_when_audit_insert_fails(): void
    {
        $administrator = $this->createUser('audit-failure-revoke-admin');
        $target = $this->createUser('audit-failure-revoke-target');

        $this->createDesignation($administrator);
        $legacyAppId = $this->createLegacyApp();

        $service = app(LegacyAppGrantService::class);
        $grant = $service->grant($target, $legacyAppId, $administrator);

        $shouldFailAuditInsert = true;

        DB::connection()->beforeExecuting(
            function (string $query) use (&$shouldFailAuditInsert): void {
                $normalizedQuery = strtolower(ltrim($query));

                if (
                    $shouldFailAuditInsert
                    && str_starts_with($normalizedQuery, 'insert')
                    && str_contains($normalizedQuery, 'audit_logs')
                ) {
                    throw new RuntimeException('Simulated audit insert failure.');
                }
            },
        );

        try {
            try {
                $service->revoke($grant, $administrator);

                $this->fail('The audit insert failure should abort the revoke transaction.');
            } catch (RuntimeException $exception) {
                $this->assertSame(
                    'Simulated audit insert failure.',
                    $exception->getMessage(),
                );
            }
        } finally {
            $shouldFailAuditInsert = false;
        }

        $this->assertTrue($grant->fresh()->isActive());
        $this->assertNull($grant->fresh()->revoked_at);
        $this->assertDatabaseCount('user_legacy_app_grants', 1);
        $this->assertDatabaseCount('legacy_app_grant_events', 1);
        $this->assertDatabaseMissing('legacy_app_grant_events', [
            'grant_id' => $grant->id,
            'event_type' => LegacyAppGrantEvent::EVENT_REVOKED,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'access.legacy_app_revoked',
            'entity_id' => $grant->id,
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

    public function test_duplicate_active_grant_is_rejected_without_creating_duplicate_records(): void
    {
        $administrator = $this->createUser('duplicate-grant-admin');
        $target = $this->createUser('duplicate-grant-target');

        $this->createDesignation($administrator);

        $legacyAppId = $this->createLegacyApp();

        $service = app(LegacyAppGrantService::class);

        $firstGrant = $service->grant(
            $target,
            $legacyAppId,
            $administrator,
        );

        try {
            $service->grant(
                $target,
                $legacyAppId,
                $administrator,
            );

            $this->fail('A duplicate active grant must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'legacy_app_id',
                $exception->errors(),
            );
        }

        $this->assertDatabaseCount('user_legacy_app_grants', 1);
        $this->assertDatabaseCount('legacy_app_grant_events', 1);

        $this->assertDatabaseHas('user_legacy_app_grants', [
            'id' => $firstGrant->id,
            'user_id' => $target->id,
            'legacy_app_id' => $legacyAppId,
            'revoked_at' => null,
        ]);

        $this->assertSame(
            1,
            DB::table('audit_logs')
                ->where('action', 'access.legacy_app_granted')
                ->count(),
        );
    }

    public function test_grant_can_be_created_again_after_previous_grant_is_revoked(): void
    {
        $administrator = $this->createUser('regrant-admin');
        $target = $this->createUser('regrant-target');

        $this->createDesignation($administrator);

        $legacyAppId = $this->createLegacyApp();

        $service = app(LegacyAppGrantService::class);

        $firstGrant = $service->grant(
            $target,
            $legacyAppId,
            $administrator,
        );

        $service->revoke($firstGrant, $administrator);

        $secondGrant = $service->grant(
            $target,
            $legacyAppId,
            $administrator,
        );

        $this->assertNotSame($firstGrant->id, $secondGrant->id);

        $this->assertDatabaseCount('user_legacy_app_grants', 2);

        $this->assertDatabaseHas('user_legacy_app_grants', [
            'id' => $firstGrant->id,
            'user_id' => $target->id,
            'legacy_app_id' => $legacyAppId,
            'revoked_by' => $administrator->id,
        ]);

        $this->assertDatabaseHas('user_legacy_app_grants', [
            'id' => $secondGrant->id,
            'user_id' => $target->id,
            'legacy_app_id' => $legacyAppId,
            'revoked_at' => null,
        ]);

        $this->assertDatabaseCount('legacy_app_grant_events', 3);

        $this->assertSame(
            2,
            DB::table('legacy_app_grant_events')
                ->where('event_type', 'granted')
                ->count(),
        );

        $this->assertSame(
            1,
            DB::table('legacy_app_grant_events')
                ->where('event_type', 'revoked')
                ->count(),
        );

        $this->assertSame(
            2,
            DB::table('audit_logs')
                ->where('action', 'access.legacy_app_granted')
                ->count(),
        );

        $this->assertSame(
            1,
            DB::table('audit_logs')
                ->where('action', 'access.legacy_app_revoked')
                ->count(),
        );
    }
}