<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_app_grant_set_application_events', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('grant_set_id')
                ->constrained('legacy_app_grant_sets')
                ->restrictOnDelete();

            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignUlid('actor_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Exact Grant Set membership at application time.
            // App IDs reference concord_platform.legacy_apps.
            $table->json('legacy_app_ids');

            $table->timestamp('occurred_at');

            $table->index(
                ['user_id', 'occurred_at'],
                'grant_set_app_events_user_time_index'
            );

            $table->index(
                ['grant_set_id', 'occurred_at'],
                'grant_set_app_events_set_time_index'
            );

            $table->index(
                ['actor_id', 'occurred_at'],
                'grant_set_app_events_actor_time_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_app_grant_set_application_events');
    }
};