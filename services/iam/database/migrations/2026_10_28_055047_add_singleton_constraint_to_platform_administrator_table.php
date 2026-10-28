<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::table('platform_administrator', function (Blueprint $table) {
            $table->unsignedTinyInteger('singleton_key')
                ->storedAs('1')
                ->unique();
        });

        DB::table('platform_administrator')
            ->where('id', '!=', 1)
            ->delete();
    }

    public function down(): void
    {
        Schema::table('platform_administrator', function (Blueprint $table) {
            $table->dropUnique(['singleton_key']);
            $table->dropColumn('singleton_key');
        });
    }
};
