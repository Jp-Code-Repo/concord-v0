<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdmin();
        $this->seedSampleCatalog();
    }

    /** First IT administrator. Credentials come from the environment. */
    private function seedAdmin(): void
    {
        $admin = config('concord.admin');

        if (empty($admin['password'])) {
            throw new \RuntimeException('IAM_ADMIN_PASSWORD is not set.');
        }

        User::firstOrCreate(
            ['username' => $admin['username']],
            [
                'email' => $admin['email'],
                'display_name' => 'IT Administrator',
                'password' => $admin['password'],
                'status' => 'active',
                'is_it_admin' => true,
            ],
        );
    }

    /**
     * Sample catalog taken from the Registrar example. Later, each app will
     * register its own catalog from a manifest instead of this seeder.
     */
    private function seedSampleCatalog(): void
    {
        $appId = $this->upsert('apps', ['key' => 'registrar'], ['name' => 'Registrar', 'sort_order' => 1]);

        $profile = $this->upsert('app_groups', ['app_id' => $appId, 'key' => 'student-profile'],
            ['name' => 'Student Profile', 'sort_order' => 1]);
        $section = $this->upsert('app_groups', ['app_id' => $appId, 'key' => 'change-section'],
            ['name' => 'Change Section', 'sort_order' => 2]);

        $blocks = [
            [$profile, 'registrar.student-profile.view', 'View', true],
            [$profile, 'registrar.student-profile.basic-info', 'Basic Info', false],
            [$profile, 'registrar.student-profile.chinese-name', 'Chinese Name', false],
            [$profile, 'registrar.student-profile.change-gender', 'Change Gender', false],
            [$section, 'registrar.change-section.view', 'View', true],
            [$section, 'registrar.change-section.change-section', 'Change Section', false],
        ];
        foreach ($blocks as $i => [$groupId, $key, $name, $isView]) {
            $this->upsert('blocks', ['key' => $key],
                ['group_id' => $groupId, 'name' => $name, 'is_view' => $isView, 'sort_order' => $i]);
        }

        $officeId = $this->upsert('offices', ['code' => 'registrar-office'], ['name' => "Registrar's Office"]);
        DB::table('office_apps')->updateOrInsert(['office_id' => $officeId, 'app_id' => $appId]);
    }

    /** Insert or update by a natural key; returns the row id. */
    private function upsert(string $table, array $match, array $values): string
    {
        $now = now();
        $existing = DB::table($table)->where($match)->first();

        if ($existing) {
            DB::table($table)->where('id', $existing->id)->update($values + ['updated_at' => $now]);

            return $existing->id;
        }

        $id = (string) Str::ulid();
        DB::table($table)->insert(
            ['id' => $id] + $match + $values + ['created_at' => $now, 'updated_at' => $now],
        );

        return $id;
    }
}
