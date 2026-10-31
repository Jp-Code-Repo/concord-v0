<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PlatformAdministrator;
use App\Models\User;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class UserCreationAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            \Illuminate\Routing\Middleware\ThrottleRequests::class
        );
    }

    public function test_administrator_account_creation_is_audited(): void
    {
        $admin = $this->createAdministrator();
        $cookie = $this->loginAs($admin);

        $response = $this->call(
            'POST',
            '/api/iam/admin/users',
            [
                'username' => 'new.employee',
                'first_name' => 'New',
                'last_name' => 'Employee',
                'types' => ['employee'],
            ],
            [SessionService::COOKIE => $cookie],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );

        $response->assertCreated();

        $targetUserId = $response->json('user.id');

        // Confirm the response contains the created account.
        $this->assertNotEmpty($targetUserId);

        // Confirm the audit event was recorded.
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'identity.user_created',
            'entity_type' => 'user',
            'entity_id' => $targetUserId,
        ]);
    }

    private function createAdministrator(): User
    {
        $person = Person::query()->create([
            'first_name' => 'Administrator',
            'last_name' => 'Test',
        ]);

        $admin = User::query()->create([
            'person_id' => $person->id,
            'username' => 'administrator',
            'password' => 'test-password',
            'status' => User::STATUS_ACTIVE,
            'is_it_admin' => false,
        ]);

        $designation = new PlatformAdministrator();
        $designation->id = 1;
        $designation->user_id = $admin->id;
        $designation->save();

        return $admin;
    }

    private function loginAs(User $admin): string
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
}
