<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('summaries', function (Blueprint $table) {
            $table->string('client_local_id', 100)->nullable()->after('transcript_id');
            $table->string('remote_id', 100)->nullable()->after('client_local_id');
            $table->string('sync_status', 20)->default('synced')->after('processing_time_ms');
            $table->timestamp('last_synced_at')->nullable()->after('sync_status');
            $table->text('sync_error')->nullable()->after('last_synced_at');
            $table->softDeletes();

            $table->index(['transcript_id', 'sync_status'], 'idx_summaries_transcript_sync');
            $table->index(['transcript_id', 'client_local_id'], 'idx_summaries_transcript_local');
        });
    }

    public function down(): void
    {
        Schema::table('summaries', function (Blueprint $table) {
            $table->dropIndex('idx_summaries_transcript_sync');
            $table->dropIndex('idx_summaries_transcript_local');
            $table->dropSoftDeletes();
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

