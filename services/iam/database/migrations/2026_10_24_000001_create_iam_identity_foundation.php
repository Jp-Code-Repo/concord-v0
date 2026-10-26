<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('name_suffix', 30)->nullable();
            $table->string('gender', 32)->nullable();
            $table->string('email')->nullable()->index();
            $table->json('preferences')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->unique()->constrained('people');
            $table->string('username', 100)->unique();
            $table->string('password');
            $table->string('status', 16)->default('active')->index();
            // Transitional implementation detail pending the Roles/Permissions decision.
            $table->boolean('is_it_admin')->default(false);
            $table->string('administrator_key', 32)->nullable()->unique();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('person_types', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->constrained('people');
            $table->string('type', 32);
            $table->timestamps();
            $table->unique(['person_id', 'type']);
            $table->index('type');
        });

        Schema::create('students', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->unique()->constrained('people');
            $table->string('student_number', 64)->unique();
            $table->timestamps();
        });

        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('student_id')->constrained('students');
            $table->string('school_year', 20);
            $table->string('grade_level', 40);
            $table->string('section', 100);
            $table->string('status', 32)->default('enrolled');
            $table->json('guardian_import_data')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'school_year']);
            $table->index(['school_year', 'grade_level', 'section']);
        });

        Schema::create('auth_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users');
            $table->char('token_hash', 64)->unique();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 32)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'revoked_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained('users');
            $table->string('action', 64)->index();
            $table->string('entity_type', 64)->nullable();
            $table->string('entity_id', 26)->nullable();
            $table->json('meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        foreach ([
            'audit_logs', 'auth_sessions', 'student_enrollments', 'students',
            'person_types', 'users', 'people',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
