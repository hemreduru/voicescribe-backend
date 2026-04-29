<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transcripts', function (Blueprint $table) {
            $table->string('client_local_id', 100)->nullable()->after('local_id');
            $table->string('remote_id', 100)->nullable()->after('client_local_id');
            $table->string('sync_status', 20)->default('synced')->after('recorded_at');
            $table->timestamp('last_synced_at')->nullable()->after('sync_status');
            $table->text('sync_error')->nullable()->after('last_synced_at');

            $table->index(['user_id', 'sync_status'], 'idx_transcripts_user_sync');
            $table->index(['user_id', 'client_local_id'], 'idx_transcripts_user_local');
        });
    }

    public function down(): void
    {
        Schema::table('transcripts', function (Blueprint $table) {
            $table->dropIndex('idx_transcripts_user_sync');
            $table->dropIndex('idx_transcripts_user_local');
            $table->dropColumn([
                'client_local_id',
                'remote_id',
                'sync_status',
                'last_synced_at',
                'sync_error',
            ]);
        });
    }
};

