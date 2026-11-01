
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_app_grant_events', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('grant_id')
                ->constrained('user_legacy_app_grants')
                ->restrictOnDelete();

            $table->string('event_type', 32);

            $table->foreignUlid('actor_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('source', 32);

            $table->json('metadata')->nullable();

            $table->timestamp('occurred_at');

            $table->index(
                ['grant_id', 'occurred_at'],
                'legacy_app_grant_events_grant_time_index'
            );

            $table->index(
                ['actor_id', 'occurred_at'],
                'legacy_app_grant_events_actor_time_index'
            );

            $table->index(
                ['event_type', 'occurred_at'],
                'legacy_app_grant_events_type_time_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_app_grant_events');
    }
};
