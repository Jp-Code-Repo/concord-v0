<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        DB::connection('mysql')->statement(
            'ALTER TABLE platform_administrator
             ADD CONSTRAINT platform_administrator_id_check
             CHECK (id = 1)'
        );

        Schema::connection('mysql')->table('platform_administrator', function ($table) {
            $table->dropUnique('platform_administrator_singleton_key_unique');
            $table->dropColumn('singleton_key');
        });
    }

    public function down(): void
    {
        DB::connection('mysql')->statement(
            'ALTER TABLE platform_administrator
             DROP CHECK platform_administrator_id_check'
        );

        Schema::connection('mysql')->table('platform_administrator', function ($table) {
            $table->unsignedTinyInteger('singleton_key')
                ->storedAs('1')
                ->unique();
        });
    }
};