
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        // Refuse to migrate if duplicate active grants already exist.
        $duplicates = DB::table('grants')
            ->select('membership_id', 'block_id')
            ->whereNull('revoked_at')
            ->groupBy('membership_id', 'block_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new RuntimeException(
                'Duplicate active grants exist. Resolve them before applying this migration.'
            );
        }

        Schema::table('grants', function (Blueprint $table) {
            $table->string('active_grant_key', 53)
                ->nullable()
                ->storedAs(
                    "CASE WHEN revoked_at IS NULL " .
                    "THEN CONCAT(membership_id, ':', block_id) " .
                    "ELSE NULL END"
                );

            $table->unique(
                'active_grant_key',
                'grants_active_membership_block_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('grants', function (Blueprint $table) {
            $table->dropUnique(
                'grants_active_membership_block_unique'
            );

            $table->dropColumn('active_grant_key');
        });
    }
};
