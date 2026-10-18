<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_head_blocks', function (Blueprint $table) {
            $table->foreignUlid('office_id')
                ->constrained('offices');

            $table->foreignUlid('block_id')
                ->constrained('blocks');

            $table->boolean('can_delegate')->default(false);

            $table->timestamps();

            $table->primary(['office_id', 'block_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_head_blocks');
    }
};