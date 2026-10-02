<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IAM data model. Nothing here is ever deleted: users are disabled, grants and
 * memberships are closed with an end date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('username', 100)->unique();
            $t->string('email')->unique();
            $t->string('display_name');
            $t->string('password');
            $t->string('status', 16)->default('active')->index(); // active | disabled
            $t->boolean('is_it_admin')->default(false);
            $t->timestamp('disabled_at')->nullable();
            $t->timestamps();
        });

        Schema::create('auth_sessions', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('user_id')->constrained('users');
            $t->char('token_hash', 64)->unique();
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent')->nullable();
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
            $t->string('revoked_reason', 32)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['user_id', 'revoked_at']);
        });

        // ---- Catalog: app -> group -> block (apps declare these; the lego pieces) ----
        Schema::create('apps', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('key', 64)->unique();
            $t->string('name');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('app_groups', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('app_id')->constrained('apps');
            $t->string('key', 64);
            $t->string('name');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->unique(['app_id', 'key']);
        });

        // Blocks are the only grantable unit. Key = app.group.block
        Schema::create('blocks', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('group_id')->constrained('app_groups');
            $t->string('key', 191)->unique();
            $t->string('name');
            $t->string('description')->nullable();
            $t->boolean('is_view')->default(false); // the group's "view" block; edit blocks depend on it
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        // ---- Offices, membership and grants ----
        Schema::create('offices', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('code', 64)->unique();
            $t->string('name');
            $t->foreignUlid('head_user_id')->nullable()->constrained('users');
            $t->timestamps();
        });

        Schema::create('office_apps', function (Blueprint $t) {
            $t->foreignUlid('office_id')->constrained('offices');
            $t->foreignUlid('app_id')->constrained('apps');
            $t->primary(['office_id', 'app_id']);
        });

        Schema::create('memberships', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('user_id')->constrained('users');
            $t->foreignUlid('office_id')->constrained('offices');
            $t->timestamp('started_at');
            $t->timestamp('ended_at')->nullable();
            $t->string('ended_reason', 32)->nullable();
            $t->index(['user_id', 'ended_at']);
        });

        Schema::create('grants', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('membership_id')->constrained('memberships');
            $t->foreignUlid('block_id')->constrained('blocks');
            $t->foreignUlid('granted_by')->constrained('users');
            $t->timestamp('granted_at');
            $t->foreignUlid('revoked_by')->nullable()->constrained('users');
            $t->timestamp('revoked_at')->nullable();
            $t->string('revoke_reason', 32)->nullable(); // head | left_office | account_disabled | it_override
            $t->index(['membership_id', 'block_id', 'revoked_at']);
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('user_id')->nullable()->constrained('users'); // actor
            $t->foreignUlid('office_id')->nullable()->constrained('offices'); // office acted under
            $t->string('action', 64)->index();
            $t->string('entity_type', 64)->nullable();
            $t->string('entity_id', 26)->nullable();
            $t->json('meta')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index('created_at');
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'grants', 'memberships', 'office_apps', 'offices', 'blocks',
            'app_groups', 'apps', 'auth_sessions', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
