<?php

namespace Tests\Feature;

use App\Models\LegacyAppGrantEvent;
use App\Models\Person;
use App\Models\User;
use App\Models\UserLegacyAppGrant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class LegacyAppGrantPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_grant_period_and_event_can_be_created(): void
    {
        $user = $this->createUser('grant-target');
        $actor = $this->createUser('grant-actor');

        $grant = $this->createGrant($user, $actor);

        $event = LegacyAppGrantEvent::query()->create([
            'grant_id' => $grant->id,
            'event_type' => LegacyAppGrantEvent::EVENT_GRANTED,
            'actor_id' => $actor->id,
            'source' => LegacyAppGrantEvent::SOURCE_INDIVIDUAL,
            'metadata' => ['reason' => 'Initial access'],
            'occurred_at' => now(),
        ]);

        $this->assertDatabaseHas('user_legacy_app_grants', [
            'id' => $grant->id,
            'user_id' => $user->id,
            'legacy_app_id' => $grant->legacy_app_id,
            'granted_by' => $actor->id,
            'revoked_at' => null,
        ]);

        $this->assertDatabaseHas('legacy_app_grant_events', [
            'id' => $event->id,
            'grant_id' => $grant->id,
            'event_type' => LegacyAppGrantEvent::EVENT_GRANTED,
            'actor_id' => $actor->id,
        ]);

        $this->assertTrue($grant->isActive());
        $this->assertCount(1, $grant->events);
        $this->assertSame($grant->id, $event->grant->id);
        $this->assertSame($actor->id, $event->actor->id);
    }

    public function test_database_rejects_duplicate_active_grants_for_the_same_user_and_app(): void
    {
        $user = $this->createUser('duplicate-target');
        $actor = $this->createUser('duplicate-actor');

        $legacyAppId = (string) Str::ulid();

        $this->createGrant($user, $actor, $legacyAppId);

        $this->expectException(QueryException::class);

        $this->createGrant($user, $actor, $legacyAppId);
    }

    public function test_revoked_grant_period_allows_a_new_active_grant_period(): void
    {
        $user = $this->createUser('regrant-target');
        $actor = $this->createUser('regrant-actor');

        $legacyAppId = (string) Str::ulid();

        $firstGrant = $this->createGrant($user, $actor, $legacyAppId);

        $firstGrant->update([
            'revoked_at' => now(),
            'revoked_by' => $actor->id,
        ]);

        $secondGrant = $this->createGrant($user, $actor, $legacyAppId);

        $this->assertFalse($firstGrant->fresh()->isActive());
        $this->assertTrue($secondGrant->isActive());

        $this->assertDatabaseCount('user_legacy_app_grants', 2);
    }

    public function test_grant_events_cannot_be_updated(): void
    {
        $user = $this->createUser('immutable-update-target');
        $actor = $this->createUser('immutable-update-actor');
        $grant = $this->createGrant($user, $actor);

        $event = $this->createGrantEvent($grant, $actor);

        $this->expectException(LogicException::class);

        $event->update([
            'metadata' => ['changed' => true],
        ]);
    }

    public function test_grant_events_cannot_be_deleted(): void
    {
        $user = $this->createUser('immutable-delete-target');
        $actor = $this->createUser('immutable-delete-actor');
        $grant = $this->createGrant($user, $actor);

        $event = $this->createGrantEvent($grant, $actor);

        $this->expectException(LogicException::class);

        $event->delete();
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

    private function createGrant(
        User $user,
        User $actor,
        ?string $legacyAppId = null
    ): UserLegacyAppGrant {
        return UserLegacyAppGrant::query()->create([
            'user_id' => $user->id,
            'legacy_app_id' => $legacyAppId ?? (string) Str::ulid(),
            'granted_at' => now(),
            'granted_by' => $actor->id,
        ]);
    }

    private function createGrantEvent(
        UserLegacyAppGrant $grant,
        User $actor
    ): LegacyAppGrantEvent {
        return LegacyAppGrantEvent::query()->create([
            'grant_id' => $grant->id,
            'event_type' => LegacyAppGrantEvent::EVENT_GRANTED,
            'actor_id' => $actor->id,
            'source' => LegacyAppGrantEvent::SOURCE_INDIVIDUAL,
            'occurred_at' => now(),
        ]);
    }
}