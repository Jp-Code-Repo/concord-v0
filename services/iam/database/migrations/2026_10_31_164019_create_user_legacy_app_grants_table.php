
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_legacy_app_grants', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // References concord_platform.legacy_apps.
            // Cross-database foreign keys are intentionally avoided.
            $table->ulid('legacy_app_id');

            $table->timestamp('granted_at');

            $table->foreignUlid('granted_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('revoked_at')->nullable();

            $table->foreignUlid('revoked_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            // 1 for active grants, NULL for revoked grants.
            // MySQL unique indexes allow multiple NULL values.
            $table->unsignedTinyInteger('active_grant_key')
                ->nullable()
                ->storedAs('IF(revoked_at IS NULL, 1, NULL)');

            $table->timestamps();

            $table->unique(
                ['user_id', 'legacy_app_id', 'active_grant_key'],
                'user_legacy_app_grants_active_unique'
            );

            $table->index(
                ['user_id', 'revoked_at'],
                'user_legacy_app_grants_user_status_index'
            );

            $table->index(
                ['legacy_app_id', 'revoked_at'],
                'user_legacy_app_grants_app_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_legacy_app_grants');
    }
};
