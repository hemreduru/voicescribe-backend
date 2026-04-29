<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('supabase_user_id')->nullable()->after('id');
            $table->index('supabase_user_id');
            $table->unique('supabase_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_supabase_user_id_unique');
            $table->dropIndex('users_supabase_user_id_index');
            $table->dropColumn('supabase_user_id');
        });
    }
};

