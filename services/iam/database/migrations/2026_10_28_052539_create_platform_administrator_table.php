<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';
    
    public function up(): void
    {
        Schema::create('platform_administrator', function (Blueprint $table) {
            // Fixed singleton identifier: this table represents one office.
            $table->unsignedTinyInteger('id')->primary();

            // The designation belongs to one specific Concord user account.
            $table->ulid('user_id')->unique();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->timestamps();
            
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_administrator');
    }
};