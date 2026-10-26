<?php

namespace Database\Seeders;

use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdmin();

        $this->call(DemoIdentitySeeder::class);
    }

    /** Provision the single platform Administrator from environment credentials. */
    private function seedAdmin(): void
    {
        $admin = config('concord.admin');

        if (empty($admin['password'])) {
            throw new \RuntimeException('IAM_ADMIN_PASSWORD is not set.');
        }

        DB::transaction(function () use ($admin): void {
            $existingAdministrator = User::query()
                ->where('administrator_key', 'ADMINISTRATOR')
                ->orWhere('is_it_admin', true)
                ->first();

            if ($existingAdministrator) {
                if ($existingAdministrator->username !== $admin['username']) {
                    throw new \RuntimeException(
                        'A different Concord Administrator already exists. Resolve the account before seeding.'
                    );
                }

                if ($existingAdministrator->status !== User::STATUS_ACTIVE) {
                    throw new \RuntimeException(
                        'The configured Concord Administrator exists but is not active. Resolve its status explicitly before seeding.'
                    );
                }

                return;
            }

            if (User::query()->where('username', $admin['username'])->exists()) {
                throw new \RuntimeException(
                    'The configured Administrator username already belongs to a non-Administrator account.'
                );
            }

            $person = Person::query()->create([
                'first_name' => 'Concord',
                'last_name' => 'Administrator',
                'email' => strtolower(trim($admin['email'])),
                'preferences' => [],
            ]);

            User::query()->create([
                'person_id' => $person->id,
                'username' => $admin['username'],
                'password' => $admin['password'],
                'status' => User::STATUS_ACTIVE,
                'is_it_admin' => true,
                'administrator_key' => 'ADMINISTRATOR',
            ]);
        });
    }
}
