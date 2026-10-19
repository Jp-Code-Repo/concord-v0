<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

use Illuminate\Routing\Middleware\ThrottleRequests;

class GrantManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function makeUser(
        string $username,
        bool $isItAdmin = false,
    ): User {
        return User::create([
            'username' => $username,
            'email' => "{$username}@example.test",
            'display_name' => $username,
            'password' => 'correct-horse-battery',
            'status' => 'active',
            'is_it_admin' => $isItAdmin,
        ]);
    }

    private function makeOffice(
        string $code,
        string $name,
        ?string $headUserId = null,
    ): string {
        $id = (string) Str::ulid();

        DB::table('offices')->insert([
            'id' => $id,
            'code' => $code,
            'name' => $name,
            'head_user_id' => $headUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function makeBlock(string $officeId): string
    {
        $appId = (string) Str::ulid();

        DB::table('apps')->insert([
            'id' => $appId,
            'key' => 'registrar',
            'name' => 'Registrar',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('office_apps')->insert([
            'office_id' => $officeId,
            'app_id' => $appId,
        ]);

        $groupId = (string) Str::ulid();

        DB::table('app_groups')->insert([
            'id' => $groupId,
            'app_id' => $appId,
            'key' => 'students',
            'name' => 'Students',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $blockId = (string) Str::ulid();

        DB::table('blocks')->insert([
            'id' => $blockId,
            'group_id' => $groupId,
            'key' => 'registrar.students.edit',
            'name' => 'Edit Students',
            'description' => null,
            'is_view' => false,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $blockId;
    }

    private function makeMembership(
        string $userId,
        string $officeId,
    ): string {
        $id = (string) Str::ulid();

        DB::table('memberships')->insert([
            'id' => $id,
            'user_id' => $userId,
            'office_id' => $officeId,
            'started_at' => now(),
            'ended_at' => null,
            'ended_reason' => null,
        ]);

        return $id;
    }

    private function loginAs(User $user): string
    {
        $response = $this->postJson('/api/iam/login', [
            'username' => $user->username,
            'password' => 'correct-horse-battery',
        ])
            ->assertOk()
            ->assertCookie(SessionService::COOKIE);

        return $response
            ->getCookie(SessionService::COOKIE, false)
            ->getValue();
    }

    private function configureHeadDelegation(
        string $officeId,
        string $blockId,
        bool $canDelegate,
    ): void {
        DB::table('office_head_blocks')->insert([
            'office_id' => $officeId,
            'block_id' => $blockId,
            'can_delegate' => $canDelegate,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_head_can_grant_a_configured_delegable_block(): void
    {
        $head = $this->makeUser('head');
        $staff = $this->makeUser('staff');

        $officeId = $this->makeOffice(
            'REG',
            'Registrar',
            $head->id,
        );

        $blockId = $this->makeBlock($officeId);
        $membershipId = $this->makeMembership(
            $staff->id,
            $officeId,
        );

        $this->configureHeadDelegation(
            $officeId,
            $blockId,
            true,
        );

        $token = $this->loginAs($head);

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson(
                "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants",
                ['block_id' => $blockId],
            )
            ->assertCreated();

        $this->assertDatabaseHas('grants', [
            'membership_id' => $membershipId,
            'block_id' => $blockId,
            'granted_by' => $head->id,
            'revoked_at' => null,
        ]);
    }

    public function test_head_cannot_grant_a_block_that_is_not_delegable(): void
    {
        $head = $this->makeUser('head');
        $staff = $this->makeUser('staff');

        $officeId = $this->makeOffice(
            'REG',
            'Registrar',
            $head->id,
        );

        $blockId = $this->makeBlock($officeId);
        $membershipId = $this->makeMembership(
            $staff->id,
            $officeId,
        );

        $this->configureHeadDelegation(
            $officeId,
            $blockId,
            false,
        );

        $token = $this->loginAs($head);

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson(
                "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants",
                ['block_id' => $blockId],
            )
            ->assertForbidden();

        $this->assertDatabaseMissing('grants', [
            'membership_id' => $membershipId,
            'block_id' => $blockId,
        ]);
    }

public function test_it_admin_can_grant_a_block_configured_as_not_delegable(): void
{
    $admin = $this->makeUser('it-admin', true);
    $staff = $this->makeUser('staff');
    $head = $this->makeUser('head');

    $officeId = $this->makeOffice(
        'REG',
        'Registrar',
        $head->id,
    );

    $blockId = $this->makeBlock($officeId);
    $membershipId = $this->makeMembership(
        $staff->id,
        $officeId,
    );

    $this->configureHeadDelegation(
        $officeId,
        $blockId,
        false,
    );

    $token = $this->loginAs($admin);

    $this->withCredentials()
        ->withUnencryptedCookie(SessionService::COOKIE, $token)
        ->postJson(
            "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants",
            ['block_id' => $blockId],
        )
        ->assertCreated();

    $this->assertDatabaseHas('grants', [
        'membership_id' => $membershipId,
        'block_id' => $blockId,
        'revoked_at' => null,
    ]);

    $grant = DB::table('grants')
        ->where('membership_id', $membershipId)
        ->where('block_id', $blockId)
        ->whereNull('revoked_at')
        ->first();

    $this->assertNotNull($grant);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $admin->id,
        'office_id' => $officeId,
        'action' => 'access.grant_created',
        'entity_type' => 'grant',
        'entity_id' => $grant->id,
    ]);
}

public function test_it_admin_can_grant_a_block_without_head_delegation_configuration(): void
{
    $admin = $this->makeUser('it-admin-no-config', true);
    $staff = $this->makeUser('staff-no-config');
    $head = $this->makeUser('head-no-config');

    $officeId = $this->makeOffice(
        'REG',
        'Registrar',
        $head->id,
    );

    $blockId = $this->makeBlock($officeId);
    $membershipId = $this->makeMembership(
        $staff->id,
        $officeId,
    );

    // Intentionally do not create an office_head_blocks row.

    $token = $this->loginAs($admin);

    $this->withCredentials()
        ->withUnencryptedCookie(SessionService::COOKIE, $token)
        ->postJson(
            "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants",
            ['block_id' => $blockId],
        )
        ->assertCreated();

    $this->assertDatabaseHas('grants', [
        'membership_id' => $membershipId,
        'block_id' => $blockId,
        'revoked_at' => null,
    ]);
}

    public function test_head_can_revoke_a_grant_without_deleting_its_history(): void
    {
        $head = $this->makeUser('head');
        $staff = $this->makeUser('staff');

        $officeId = $this->makeOffice(
            'REG',
            'Registrar',
            $head->id,
        );

        $blockId = $this->makeBlock($officeId);
        $membershipId = $this->makeMembership(
            $staff->id,
            $officeId,
        );

        $this->configureHeadDelegation(
            $officeId,
            $blockId,
            true,
        );

        $token = $this->loginAs($head);

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson(
                "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants",
                ['block_id' => $blockId],
            )
            ->assertCreated();

        $grant = DB::table('grants')
            ->where('membership_id', $membershipId)
            ->where('block_id', $blockId)
            ->whereNull('revoked_at')
            ->first();

        $this->assertNotNull($grant);

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson(
                "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants/{$grant->id}/revoke",
            )
            ->assertOk()
            ->assertJson(['message' => 'Grant revoked.']);

        $revokedGrant = DB::table('grants')
            ->where('id', $grant->id)
            ->first();

        $this->assertNotNull($revokedGrant);
        $this->assertSame($head->id, $revokedGrant->revoked_by);
        $this->assertNotNull($revokedGrant->revoked_at);
        $this->assertSame('head', $revokedGrant->revoke_reason);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'access.grant_revoked',
            'user_id' => $head->id,
            'office_id' => $officeId,
            'entity_type' => 'grant',
            'entity_id' => $grant->id,
        ]);
    }

    public function test_duplicate_active_grant_is_rejected(): void
    {
        $head = $this->makeUser('head');
        $staff = $this->makeUser('staff');

        $officeId = $this->makeOffice(
            'REG',
            'Registrar',
            $head->id,
        );

        $blockId = $this->makeBlock($officeId);
        $membershipId = $this->makeMembership(
            $staff->id,
            $officeId,
        );

        $this->configureHeadDelegation(
            $officeId,
            $blockId,
            true,
        );

        $token = $this->loginAs($head);

        $url = "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants";

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson($url, ['block_id' => $blockId])
            ->assertCreated();

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson($url, ['block_id' => $blockId])
            ->assertStatus(409);

        $this->assertSame(
            1,
            DB::table('grants')
                ->where('membership_id', $membershipId)
                ->where('block_id', $blockId)
                ->whereNull('revoked_at')
                ->count(),
        );
    }

        public function test_revoking_a_grant_invalidates_the_staff_access_cache(): void
    {
        $head = $this->makeUser('head');
        $staff = $this->makeUser('staff');

        $officeId = $this->makeOffice('REG', 'Registrar', $head->id);
        $blockId = $this->makeBlock($officeId);
        $membershipId = $this->makeMembership($staff->id, $officeId);

        $this->configureHeadDelegation($officeId, $blockId, true);
        $headToken = $this->loginAs($head);

        $grantResponse = $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $headToken)
            ->postJson(
                "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants",
                ['block_id' => $blockId],
            )
            ->assertCreated();

        $grantId = $grantResponse->json('grant.id');
        $access = app(\App\Services\AccessService::class);

        // Prime the cache while the grant is active.
        $before = $access->forUser($staff->id, false);
        $staffOfficeBefore = collect($before['offices'])
            ->firstWhere('office_id', $officeId);

        $this->assertNotNull($staffOfficeBefore);
        $this->assertContains(
            'registrar.students.edit',
            $staffOfficeBefore['blocks'],
        );

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $headToken)
            ->postJson(
                "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants/{$grantId}/revoke",
            )
            ->assertOk();

        // The revoked Block must disappear immediately.
        $after = $access->forUser($staff->id, false);
        $staffOfficeAfter = collect($after['offices'])
            ->firstWhere('office_id', $officeId);

        $this->assertNotNull($staffOfficeAfter);
        $this->assertNotContains(
            'registrar.students.edit',
            $staffOfficeAfter['blocks'],
        );
    }

public function test_ordinary_staff_cannot_grant_access(): void
    {
        $head = $this->makeUser('head');
        $staff = $this->makeUser('staff');
        $target = $this->makeUser('target');

        $officeId = $this->makeOffice(
            'REG',
            'Registrar',
            $head->id,
        );

        $blockId = $this->makeBlock($officeId);
        $membershipId = $this->makeMembership(
            $target->id,
            $officeId,
        );

        $this->configureHeadDelegation(
            $officeId,
            $blockId,
            true,
        );

        $token = $this->loginAs($staff);

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson(
                "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants",
                ['block_id' => $blockId],
            )
            ->assertForbidden();

        $this->assertSame(
            0,
            DB::table('grants')
                ->where('membership_id', $membershipId)
                ->where('block_id', $blockId)
                ->count(),
        );
    }

    public function test_ended_membership_cannot_receive_a_grant(): void
    {
        $head = $this->makeUser('head');
        $staff = $this->makeUser('staff');

        $officeId = $this->makeOffice(
            'REG',
            'Registrar',
            $head->id,
        );

        $blockId = $this->makeBlock($officeId);
        $membershipId = $this->makeMembership(
            $staff->id,
            $officeId,
        );

        $this->configureHeadDelegation(
            $officeId,
            $blockId,
            true,
        );

        DB::table('memberships')
            ->where('id', $membershipId)
            ->update([
                'ended_at' => now(),
                'ended_reason' => 'left_office',
            ]);

        $token = $this->loginAs($head);

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson(
                "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants",
                ['block_id' => $blockId],
            )
            ->assertNotFound();

        $this->assertSame(
            0,
            DB::table('grants')
                ->where('membership_id', $membershipId)
                ->where('block_id', $blockId)
                ->count(),
        );
    }

    // NEW
public function test_it_admin_can_grant_a_configured_delegable_block(): void
{
    $admin = $this->makeUser('it-admin', true);
    $staff = $this->makeUser('staff');

    $officeId = $this->makeOffice(
        'REG',
        'Registrar',
    );

    $blockId = $this->makeBlock($officeId);
    $membershipId = $this->makeMembership(
        $staff->id,
        $officeId,
    );

    $this->configureHeadDelegation(
        $officeId,
        $blockId,
        true,
    );

    $token = $this->loginAs($admin);

    $this->withCredentials()
        ->withUnencryptedCookie(SessionService::COOKIE, $token)
        ->postJson(
            "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants",
            ['block_id' => $blockId],
        )
        ->assertCreated();

    $this->assertSame(
        1,
        DB::table('grants')
            ->where('membership_id', $membershipId)
            ->where('block_id', $blockId)
            ->whereNull('revoked_at')
            ->count(),
    );
}

// NEW
public function test_already_revoked_grant_cannot_be_revoked_again(): void
{
    $head = $this->makeUser('head');
    $staff = $this->makeUser('staff');

    $officeId = $this->makeOffice(
        'REG',
        'Registrar',
        $head->id,
    );

    $blockId = $this->makeBlock($officeId);
    $membershipId = $this->makeMembership(
        $staff->id,
        $officeId,
    );

    $this->configureHeadDelegation(
        $officeId,
        $blockId,
        true,
    );

    $token = $this->loginAs($head);

    $grantResponse = $this->withCredentials()
        ->withUnencryptedCookie(SessionService::COOKIE, $token)
        ->postJson(
            "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants",
            ['block_id' => $blockId],
        )
        ->assertCreated();

    $grantId = $grantResponse->json('grant.id');

    $url = "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants/{$grantId}/revoke";

    $this->withCredentials()
        ->withUnencryptedCookie(SessionService::COOKIE, $token)
        ->postJson($url)
        ->assertOk();

    $originalRevocation = DB::table('grants')
        ->where('id', $grantId)
        ->first();

    $this->withCredentials()
        ->withUnencryptedCookie(SessionService::COOKIE, $token)
        ->postJson($url)
        ->assertStatus(409);

    $currentRevocation = DB::table('grants')
        ->where('id', $grantId)
        ->first();

    $this->assertSame(
        $originalRevocation->revoked_at,
        $currentRevocation->revoked_at,
    );

    $this->assertSame(
        $originalRevocation->revoked_by,
        $currentRevocation->revoked_by,
    );

    $this->assertSame(
        $originalRevocation->revoke_reason,
        $currentRevocation->revoke_reason,
    );
}

// NEW
public function test_grant_creation_writes_an_audit_record(): void
{
    $head = $this->makeUser('head');
    $staff = $this->makeUser('staff');

    $officeId = $this->makeOffice(
        'REG',
        'Registrar',
        $head->id,
    );

    $blockId = $this->makeBlock($officeId);
    $membershipId = $this->makeMembership(
        $staff->id,
        $officeId,
    );

    $this->configureHeadDelegation(
        $officeId,
        $blockId,
        true,
    );

    $token = $this->loginAs($head);

    $response = $this->withCredentials()
        ->withUnencryptedCookie(SessionService::COOKIE, $token)
        ->postJson(
            "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants",
            ['block_id' => $blockId],
        )
        ->assertCreated();

    $grantId = $response->json('grant.id');

$this->assertDatabaseHas('audit_logs', [
    'user_id' => $head->id, // CHANGED: was actor_user_id
    'office_id' => $officeId,
    'action' => 'access.grant_created',
    'entity_type' => 'grant',
    'entity_id' => $grantId,
]);
}

// NEW
public function test_grant_cannot_use_a_block_from_another_office(): void
{
    $head = $this->makeUser('head');
    $staff = $this->makeUser('staff');

    $officeA = $this->makeOffice(
        'REG',
        'Registrar',
        $head->id,
    );

    $officeB = $this->makeOffice(
        'LIB',
        'Library',
    );

    $blockId = $this->makeBlock($officeB);
    $membershipId = $this->makeMembership(
        $staff->id,
        $officeA,
    );

    $this->configureHeadDelegation(
        $officeB,
        $blockId,
        true,
    );

    $token = $this->loginAs($head);

    $this->withCredentials()
        ->withUnencryptedCookie(SessionService::COOKIE, $token)
        ->postJson(
            "/api/iam/offices/{$officeA}/memberships/{$membershipId}/grants",
            ['block_id' => $blockId],
        )
        ->assertStatus(422);

    $this->assertSame(
        0,
        DB::table('grants')
            ->where('membership_id', $membershipId)
            ->where('block_id', $blockId)
            ->count(),
    );
}

public function test_it_admin_cannot_grant_to_an_ended_membership(): void
{
    $admin = $this->makeUser('it-admin-ended-membership', true);
    $staff = $this->makeUser('staff-ended-membership');

    $officeId = $this->makeOffice(
        'REG',
        'Registrar',
    );

    $blockId = $this->makeBlock($officeId);
    $membershipId = $this->makeMembership(
        $staff->id,
        $officeId,
    );

    DB::table('memberships')
        ->where('id', $membershipId)
        ->update([
            'ended_at' => now(),
            'ended_reason' => 'left_office',
        ]);

    $token = $this->loginAs($admin);

    $this->withCredentials()
        ->withUnencryptedCookie(SessionService::COOKIE, $token)
        ->postJson(
            "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants",
            ['block_id' => $blockId],
        )
        ->assertNotFound();

    $this->assertSame(
        0,
        DB::table('grants')
            ->where('membership_id', $membershipId)
            ->where('block_id', $blockId)
            ->count(),
    );
}

public function test_it_admin_cannot_grant_a_block_from_another_office(): void
{
    $admin = $this->makeUser('it-admin-cross-office', true);
    $staff = $this->makeUser('staff-cross-office');

    $officeA = $this->makeOffice(
        'REG',
        'Registrar',
    );

    $officeB = $this->makeOffice(
        'LIB',
        'Library',
    );

    $blockId = $this->makeBlock($officeB);
    $membershipId = $this->makeMembership(
        $staff->id,
        $officeA,
    );

    $token = $this->loginAs($admin);

    $this->withCredentials()
        ->withUnencryptedCookie(SessionService::COOKIE, $token)
        ->postJson(
            "/api/iam/offices/{$officeA}/memberships/{$membershipId}/grants",
            ['block_id' => $blockId],
        )
        ->assertStatus(422);

    $this->assertSame(
        0,
        DB::table('grants')
            ->where('membership_id', $membershipId)
            ->where('block_id', $blockId)
            ->count(),
    );
}

public function test_grant_revocation_writes_an_audit_record(): void
{
    $head = $this->makeUser('head');
    $staff = $this->makeUser('staff');

    $officeId = $this->makeOffice('REG', 'Registrar', $head->id);
    $blockId = $this->makeBlock($officeId);
    $membershipId = $this->makeMembership($staff->id, $officeId);

    $this->configureHeadDelegation($officeId, $blockId, true);
    $token = $this->loginAs($head);

    $grantResponse = $this->withCredentials()
        ->withUnencryptedCookie(SessionService::COOKIE, $token)
        ->postJson(
            "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants",
            ['block_id' => $blockId],
        )
        ->assertCreated();

    $grantId = $grantResponse->json('grant.id');

    $this->withCredentials()
        ->withUnencryptedCookie(SessionService::COOKIE, $token)
        ->postJson(
            "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants/{$grantId}/revoke",
        )
        ->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $head->id,
        'office_id' => $officeId,
        'action' => 'access.grant_revoked',
        'entity_type' => 'grant',
        'entity_id' => $grantId,
    ]);
}

public function test_ordinary_staff_cannot_revoke_a_grant(): void
{
    $head = $this->makeUser('head');
    $staff = $this->makeUser('staff');
    $target = $this->makeUser('target');

    $officeId = $this->makeOffice('REG', 'Registrar', $head->id);
    $blockId = $this->makeBlock($officeId);
    $membershipId = $this->makeMembership($target->id, $officeId);

    $this->configureHeadDelegation($officeId, $blockId, true);
    $headToken = $this->loginAs($head);

    $grantResponse = $this->withCredentials()
        ->withUnencryptedCookie(SessionService::COOKIE, $headToken)
        ->postJson(
            "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants",
            ['block_id' => $blockId],
        )
        ->assertCreated();

    $grantId = $grantResponse->json('grant.id');
    $staffToken = $this->loginAs($staff);

    $this->withCredentials()
        ->withUnencryptedCookie(SessionService::COOKIE, $staffToken)
        ->postJson(
            "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants/{$grantId}/revoke",
        )
        ->assertForbidden();

    $this->assertDatabaseHas('grants', [
        'id' => $grantId,
        'revoked_at' => null,
        'revoked_by' => null,
    ]);
}

    public function test_creating_a_grant_invalidates_the_staff_access_cache(): void
    {
        $head = $this->makeUser('head');
        $staff = $this->makeUser('staff');

        $officeId = $this->makeOffice('REG', 'Registrar', $head->id);
        $blockId = $this->makeBlock($officeId);
        $membershipId = $this->makeMembership($staff->id, $officeId);

        $this->configureHeadDelegation($officeId, $blockId, true);

        $access = app(\App\Services\AccessService::class);

        // Prime the cache before the grant exists.
        $before = $access->forUser($staff->id, false);
        $staffOfficeBefore = collect($before['offices'])
            ->firstWhere('office_id', $officeId);

        $this->assertNotNull($staffOfficeBefore);
        $this->assertNotContains(
            'registrar.students.edit',
            $staffOfficeBefore['blocks'],
        );

        $token = $this->loginAs($head);

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson(
                "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants",
                ['block_id' => $blockId],
            )
            ->assertCreated();

        // The new Block must appear without waiting for cache expiry.
        $after = $access->forUser($staff->id, false);
        $staffOfficeAfter = collect($after['offices'])
            ->firstWhere('office_id', $officeId);

        $this->assertNotNull($staffOfficeAfter);
        $this->assertContains(
            'registrar.students.edit',
            $staffOfficeAfter['blocks'],
        );
    }


    public function test_invalid_revocation_reason_is_rejected_without_revoking_grant(): void
    {
        $head = $this->makeUser('head');
        $staff = $this->makeUser('staff');
        $officeId = $this->makeOffice('REG', 'Registrar', $head->id);
        $blockId = $this->makeBlock($officeId);
        $membershipId = $this->makeMembership($staff->id, $officeId);

        $this->configureHeadDelegation($officeId, $blockId, true);
        $token = $this->loginAs($head);

        $grantResponse = $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson(
                "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants",
                ['block_id' => $blockId],
            )
            ->assertCreated();

        $grantId = $grantResponse->json('grant.id');

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson(
                "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants/{$grantId}/revoke",
                ['reason' => 'not_a_valid_reason'],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->assertDatabaseHas('grants', [
            'id' => $grantId,
            'revoked_at' => null,
            'revoked_by' => null,
            'revoke_reason' => null,
        ]);
    }

    public function test_all_documented_revocation_reasons_are_accepted(): void
    {
        $head = $this->makeUser('head');
        $staff = $this->makeUser('staff');
        $officeId = $this->makeOffice('REG', 'Registrar', $head->id);
        $blockId = $this->makeBlock($officeId);
        $membershipId = $this->makeMembership($staff->id, $officeId);

        $this->configureHeadDelegation($officeId, $blockId, true);
        $token = $this->loginAs($head);
        $reasons = [
            'head',
            'left_office',
            'account_disabled',
            'it_override',
        ];

        foreach ($reasons as $reason) {
            $grantResponse = $this->withCredentials()
                ->withUnencryptedCookie(SessionService::COOKIE, $token)
                ->postJson(
                    "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants",
                    ['block_id' => $blockId],
                )
                ->assertCreated();

            $grantId = $grantResponse->json('grant.id');

            $this->withCredentials()
                ->withUnencryptedCookie(SessionService::COOKIE, $token)
                ->postJson(
                    "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants/{$grantId}/revoke",
                    ['reason' => $reason],
                )
                ->assertOk()
                ->assertJson(['message' => 'Grant revoked.']);

            $revokedGrant = DB::table('grants')
                ->where('id', $grantId)
                ->first();

            $this->assertNotNull($revokedGrant);
            $this->assertNotNull($revokedGrant->revoked_at);
            $this->assertSame($reason, $revokedGrant->revoke_reason);
        }
    }

    public function test_database_rejects_duplicate_active_grants(): void
    {
        $head = $this->makeUser('head');
        $staff = $this->makeUser('staff');
        $officeId = $this->makeOffice('REG', 'Registrar', $head->id);
        $blockId = $this->makeBlock($officeId);
        $membershipId = $this->makeMembership($staff->id, $officeId);

        $this->configureHeadDelegation($officeId, $blockId, true);
        $token = $this->loginAs($head);

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson(
                "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants",
                ['block_id' => $blockId],
            )
            ->assertCreated();

        try {
            DB::table('grants')->insert([
                'id' => (string) Str::ulid(),
                'membership_id' => $membershipId,
                'block_id' => $blockId,
                'granted_by' => $head->id,
                'granted_at' => now(),
                'revoked_by' => null,
                'revoked_at' => null,
                'revoke_reason' => null,
            ]);
        } catch (QueryException $exception) {
            $this->assertSame(1062, (int) ($exception->errorInfo[1] ?? 0));
            $this->assertStringContainsString(
                'grants_active_membership_block_unique',
                $exception->getMessage(),
            );

            return;
        }

        $this->fail('The database allowed a duplicate active grant.');
    }

    public function test_new_active_grant_can_be_created_after_previous_grant_is_revoked(): void
    {
        $head = $this->makeUser('head');
        $staff = $this->makeUser('staff');
        $officeId = $this->makeOffice('REG', 'Registrar', $head->id);
        $blockId = $this->makeBlock($officeId);
        $membershipId = $this->makeMembership($staff->id, $officeId);

        $this->configureHeadDelegation($officeId, $blockId, true);
        $token = $this->loginAs($head);
        $url = "/api/iam/offices/{$officeId}/memberships/{$membershipId}/grants";

        $firstGrantResponse = $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson($url, ['block_id' => $blockId])
            ->assertCreated();

        $firstGrantId = $firstGrantResponse->json('grant.id');

        $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson("{$url}/{$firstGrantId}/revoke")
            ->assertOk();

        $secondGrantResponse = $this->withCredentials()
            ->withUnencryptedCookie(SessionService::COOKIE, $token)
            ->postJson($url, ['block_id' => $blockId])
            ->assertCreated();

        $secondGrantId = $secondGrantResponse->json('grant.id');

        $this->assertNotSame($firstGrantId, $secondGrantId);
        $this->assertDatabaseHas('grants', [
            'id' => $firstGrantId,
            'revoke_reason' => 'head',
        ]);
        $this->assertDatabaseHas('grants', [
            'id' => $secondGrantId,
            'revoked_at' => null,
        ]);
        $this->assertSame(
            1,
            DB::table('grants')
                ->where('membership_id', $membershipId)
                ->where('block_id', $blockId)
                ->whereNull('revoked_at')
                ->count(),
        );
    }

}
