<?php

namespace Database\Seeders;

use App\Models\Person;
use App\Models\PlatformAdministrator;
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

    /**
     * Provision the single platform Administrator from environment credentials.
     */
    private function seedAdmin(): void
    {
        $admin = config('concord.admin');

        if (empty($admin['password'])) {
            throw new \RuntimeException('IAM_ADMIN_PASSWORD is not set.');
        }

        DB::connection('mysql')->transaction(function () use ($admin): void {
            $legacyAdministrators = User::query()
                ->where('administrator_key', 'ADMINISTRATOR')
                ->orWhere('is_it_admin', true)
                ->get();

            if ($legacyAdministrators->count() > 1) {
                throw new \RuntimeException(
                    'Multiple legacy Concord Administrator accounts exist. Resolve the conflict explicitly before seeding.'
                );
            }

            $designation = PlatformAdministrator::query()
                ->whereKey(1)
                ->first();

            if ($designation) {
                $administrator = User::query()->find($designation->user_id);

                if (! $administrator) {
                    throw new \RuntimeException(
                        'The Concord Administrator designation references a missing account.'
                    );
                }

                if ($administrator->username !== $admin['username']) {
                    throw new \RuntimeException(
                        'A different Concord Administrator is designated. Resolve the account explicitly before seeding.'
                    );
                }

                if ($administrator->status !== User::STATUS_ACTIVE) {
                    throw new \RuntimeException(
                        'The designated Concord Administrator is not active. Resolve its status explicitly before seeding.'
                    );
                }

                if (
                    $legacyAdministrators->isNotEmpty()
                    && $legacyAdministrators->first()->id !== $administrator->id
                ) {
                    throw new \RuntimeException(
                        'The legacy Administrator markers conflict with the platform designation.'
                    );
                }
            } else {
                $administrator = $legacyAdministrators->first();

                if ($administrator) {
                    if ($administrator->username !== $admin['username']) {
                        throw new \RuntimeException(
                            'A different Concord Administrator already exists. Resolve the account before seeding.'
                        );
                    }

                    if ($administrator->status !== User::STATUS_ACTIVE) {
                        throw new \RuntimeException(
                            'The configured Concord Administrator exists but is not active. Resolve its status explicitly before seeding.'
                        );
                    }
                } else {
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

                    $administrator = User::query()->create([
                        'person_id' => $person->id,
                        'username' => $admin['username'],
                        'password' => $admin['password'],
                        'status' => User::STATUS_ACTIVE,
                        'is_it_admin' => true,
                        'administrator_key' => 'ADMINISTRATOR',
                    ]);
                }

                DB::table('platform_administrator')->insert([
                    'id' => 1,
                    'user_id' => $administrator->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Keep legacy markers consistent during the authorization transition.
            if (
                ! $administrator->is_it_admin
                || $administrator->administrator_key !== 'ADMINISTRATOR'
            ) {
                $administrator->is_it_admin = true;
                $administrator->administrator_key = 'ADMINISTRATOR';
                $administrator->save();
            }
        });
    }
}