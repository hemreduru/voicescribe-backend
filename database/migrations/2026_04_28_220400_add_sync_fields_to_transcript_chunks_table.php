<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transcript_chunks', function (Blueprint $table) {
            $table->foreignId('speaker_id')->nullable()->after('speaker_label')->constrained('speakers')->nullOnDelete();
            $table->decimal('speaker_confidence', 5, 4)->nullable()->after('speaker_id');
            $table->string('speaker_analysis_status', 20)->default('pending')->after('speaker_confidence');
            $table->string('client_local_id', 100)->nullable()->after('confidence');
            $table->string('remote_id', 100)->nullable()->after('client_local_id');
            $table->string('sync_status', 20)->default('synced')->after('remote_id');
            $table->timestamp('last_synced_at')->nullable()->after('sync_status');
            $table->text('sync_error')->nullable()->after('last_synced_at');
            $table->softDeletes();

            $table->index(['transcript_id', 'sync_status'], 'idx_chunks_transcript_sync');
            $table->index(['transcript_id', 'client_local_id'], 'idx_chunks_transcript_local');
            $table->index(['speaker_analysis_status'], 'idx_chunks_analysis_status');
        });
    }

    public function down(): void
    {
        Schema::table('transcript_chunks', function (Blueprint $table) {
            $table->dropIndex('idx_chunks_transcript_sync');
            $table->dropIndex('idx_chunks_transcript_local');
            $table->dropIndex('idx_chunks_analysis_status');
            $table->dropConstrainedForeignId('speaker_id');
            $table->dropSoftDeletes();
            $table->dropColumn([
                'speaker_confidence',
                'speaker_analysis_status',
                'client_local_id',
                'remote_id',
                'sync_status',
                'last_synced_at',
                'sync_error',
            ]);
        });
    }
};

