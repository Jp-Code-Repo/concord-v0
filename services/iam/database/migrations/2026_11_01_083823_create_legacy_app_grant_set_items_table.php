<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_app_grant_set_items', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('grant_set_id')
                ->constrained('legacy_app_grant_sets')
                ->cascadeOnDelete();

            // Application directory is in concord_platform.
            // No cross-database foreign key.
            $table->ulid('legacy_app_id');

            $table->timestamps();

            $table->unique(
                ['grant_set_id', 'legacy_app_id'],
                'legacy_app_grant_set_items_unique'
            );

            $table->index('legacy_app_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_app_grant_set_items');
    }
};