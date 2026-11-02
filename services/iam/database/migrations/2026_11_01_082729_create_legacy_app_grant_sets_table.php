<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_app_grant_sets', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('name', 150);
            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_app_grant_sets');
    }
};