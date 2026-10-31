<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PlatformAdministrator;
use App\Models\User;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;

class UserManagementListingTest extends TestCase
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_administrator_can_search_users_by_username(): void
    {
        $admin = $this->createUser('administrator');
        $this->designateAdministrator($admin);

        $this->createUser('alex.santos');
        $this->createUser('maria.reyes');

        $cookie = $this->administratorCookie($admin);

        $response = $this->getUsers([
            'search' => 'alex.santos',
        ], $cookie);

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.username', 'alex.santos');
    }

    public function test_administrator_can_search_users_by_person_name(): void
    {
        $admin = $this->createUser('administrator');
        $this->designateAdministrator($admin);

        $this->createUser('alex.santos', [
            'first_name' => 'Alex',
            'last_name' => 'Santos',
        ]);

        $this->createUser('maria.reyes', [
            'first_name' => 'Maria',
            'last_name' => 'Reyes',
        ]);

        $cookie = $this->administratorCookie($admin);

        $response = $this->getUsers([
            'search' => 'Santos',
        ], $cookie);

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.username', 'alex.santos');
    }

    public function test_administrator_can_filter_users_by_status(): void
    {
        $admin = $this->createUser('administrator');
        $this->designateAdministrator($admin);

        $this->createUser('active.user');
        $this->createUser('disabled.user', [
            'status' => User::STATUS_DISABLED,
        ]);
        $this->createUser('deactivated.user', [
            'status' => User::STATUS_DEACTIVATED,
        ]);

        $cookie = $this->administratorCookie($admin);

        $response = $this->getUsers([
            'status' => User::STATUS_DISABLED,
        ], $cookie);

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.username', 'disabled.user');
    }

    public function test_listing_preserves_pagination_and_existing_response_fields(): void
    {
        $admin = $this->createUser('administrator');
        $this->designateAdministrator($admin);

        $this->createUser('alex.santos', [
            'first_name' => 'Alex',
            'last_name' => 'Santos',
        ]);

        $cookie = $this->administratorCookie($admin);

        $response = $this->getUsers([
            'per_page' => 1,
            'page' => 1,
        ], $cookie);

        $response->assertOk()
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    [
                        'id',
                        'person_id',
                        'username',
                        'status',
                        'created_at',
                        'person' => [
                            'id',
                            'first_name',
                            'last_name',
                            'display_name',
                            'types',
                            'student_number',
                        ],
                    ],
                ],
                'per_page',
                'total',
                'last_page',
            ]);
    }

    private function createUser(
        string $username,
        array $attributes = []
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

    private function getUsers(array $query, string $cookie)
    {
        return $this->call(
            'GET',
            '/api/iam/admin/users',
            $query,
            [SessionService::COOKIE => $cookie],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );
    }
}