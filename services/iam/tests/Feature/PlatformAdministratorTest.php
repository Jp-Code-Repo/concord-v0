<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireItAdmin;
use App\Models\Person;
use App\Models\PlatformAdministrator;
use App\Models\User;
use App\Services\PlatformAdministratorService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PlatformAdministratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_designated_user_is_recognized_as_platform_administrator(): void
    {
        $administrator = $this->createUser('administrator');
        $ordinaryUser = $this->createUser('ordinary');

        $this->createDesignation($administrator);

        $service = app(PlatformAdministratorService::class);

        $this->assertTrue($service->isDesignated($administrator->id));
        $this->assertFalse($service->isDesignated($ordinaryUser->id));
        $this->assertSame(
            $administrator->id,
            $service->designatedUserId()
        );
    }

    public function test_database_rejects_a_second_administrator_designation(): void
    {
        $firstUser = $this->createUser('first-admin');
        $secondUser = $this->createUser('second-admin');

        $this->createDesignation($firstUser);

        $secondDesignation = new PlatformAdministrator();
        $secondDesignation->id = 2;
        $secondDesignation->user_id = $secondUser->id;

        $this->expectException(QueryException::class);

        $secondDesignation->save();
    }

    public function test_database_rejects_an_administrator_designation_with_an_invalid_id(): void
    {
        $user = $this->createUser('invalid-id-admin');

        $designation = new PlatformAdministrator();
        $designation->id = 2;
        $designation->user_id = $user->id;

        $this->expectException(QueryException::class);

        $designation->save();
    }

    public function test_a_user_cannot_hold_multiple_administrator_designations(): void
    {
        $user = $this->createUser('duplicate-admin');

        $this->createDesignation($user);

        $duplicate = new PlatformAdministrator();
        $duplicate->id = 1;
        $duplicate->user_id = $user->id;

        $this->expectException(QueryException::class);

        $duplicate->save();
    }

    public function test_administrator_designation_is_not_inferred_from_the_legacy_flag(): void
    {
        $legacyAdmin = $this->createUser('legacy-admin', true);
        $ordinaryUser = $this->createUser('ordinary-user', false);

        $service = app(PlatformAdministratorService::class);

        $this->assertFalse($service->isDesignated($legacyAdmin->id));
        $this->assertFalse($service->isDesignated($ordinaryUser->id));
        $this->assertNull($service->designatedUserId());
    }

    public function test_designation_remains_associated_with_the_same_user_account(): void
    {
        $administrator = $this->createUser('stable-admin');
        $this->createDesignation($administrator);

        $service = app(PlatformAdministratorService::class);

        $this->assertSame($administrator->id, $service->designatedUserId());

        // Ordinary account status changes must not transfer the designation.
        $administrator->status = User::STATUS_DEACTIVATED;
        $administrator->save();

        $this->assertSame($administrator->id, $service->designatedUserId());
        $this->assertTrue($service->isDesignated($administrator->id));
    }

    public function test_designated_user_cannot_be_deleted_while_designation_exists(): void
    {
        $administrator = $this->createUser('protected-admin');
        $this->createDesignation($administrator);

        $this->expectException(QueryException::class);

        $administrator->delete();
    }

    public function test_admin_middleware_uses_the_designation_instead_of_the_legacy_flag(): void
    {
        $legacyFlagUser = $this->createUser('legacy-flag-admin', true);
        $designatedUser = $this->createUser('designated-admin', false);

        $this->createDesignation($designatedUser);

        $middleware = app(RequireItAdmin::class);

        // A legacy admin flag must not grant Administrator access.
        $legacyRequest = Request::create('/api/iam/admin/users', 'GET');
        $legacyRequest->attributes->set('auth', [
            'user_id' => $legacyFlagUser->id,
        ]);

        $legacyResponse = $middleware->handle(
            $legacyRequest,
            fn ($request) => response()->json(['message' => 'Authorized.'])
        );

        $this->assertSame(403, $legacyResponse->getStatusCode());

        // The designated Administrator must be authorized even without the legacy flag.
        $designatedRequest = Request::create('/api/iam/admin/users', 'GET');
        $designatedRequest->attributes->set('auth', [
            'user_id' => $designatedUser->id,
        ]);

        $designatedResponse = $middleware->handle(
            $designatedRequest,
            fn ($request) => response()->json(['message' => 'Authorized.'])
        );

        $this->assertSame(200, $designatedResponse->getStatusCode());
    }

    private function createDesignation(User $user): PlatformAdministrator
    {
        $designation = new PlatformAdministrator();
        $designation->id = 1;
        $designation->user_id = $user->id;
        $designation->save();

        return $designation;
    }

    private function createUser(
        string $username,
        bool $legacyAdmin = false
    ): User {
        $person = Person::query()->create([
            'first_name' => ucfirst($username),
            'last_name' => 'Test',
        ]);

        return User::query()->create([
            'person_id' => $person->id,
            'username' => $username,
            'password' => 'test-password',
            'is_it_admin' => $legacyAdmin,
        ]);
    }
}
