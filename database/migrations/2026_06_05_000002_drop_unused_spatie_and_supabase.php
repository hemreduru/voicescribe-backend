<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove dead schema:
 *  - spatie/laravel-permission tables: the package was wired up (HasRoles trait)
 *    but no role/permission is ever assigned or checked anywhere in the app.
 *  - users.supabase_user_id: a leftover from the abandoned Supabase auth; never
 *    populated or read after the migration to custom Sanctum auth.
 *
 * Framework tables (sessions, cache, cache_locks, jobs, job_batches,
 * failed_jobs) are intentionally kept: SESSION_DRIVER, CACHE_STORE and
 * QUEUE_CONNECTION are all set to `database`, so those tables are in use.
 *
 * After applying this, also run: composer remove spatie/laravel-permission
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop pivot/child tables before the parents they reference.
        foreach ([
            'role_has_permissions',
            'model_has_roles',
            'model_has_permissions',
            'roles',
            'permissions',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        if (Schema::hasColumn('users', 'supabase_user_id')) {
            Schema::table('users', function (Blueprint $table) {
                // Drop the index + unique before the column. MySQL drops them
                // implicitly with the column, but SQLite rebuilds the table and
                // fails on the dangling index, so be explicit for portability.
                $table->dropUnique('users_supabase_user_id_unique');
                $table->dropIndex('users_supabase_user_id_index');
                $table->dropColumn('supabase_user_id');
            });
        }
    }

    public function down(): void
    {
        // The spatie tables are recreated by re-publishing the package:
        //   composer require spatie/laravel-permission
        //   php artisan vendor:publish --provider="Spatie\\Permission\\PermissionServiceProvider"
        //   php artisan migrate
        // The supabase column is intentionally not restored (dead data).
        if (! Schema::hasColumn('users', 'supabase_user_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->uuid('supabase_user_id')->nullable()->after('id');
            });
        }
    }
};
