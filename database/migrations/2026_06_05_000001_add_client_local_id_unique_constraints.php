<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enforce sync idempotency: a client_local_id must map to exactly one server
 * row per owner. Previously these columns were only indexed (non-unique),
 * which allowed duplicate rows to accumulate on the server and made push/pull
 * matching ambiguous. NULL client_local_id (legacy rows) is still allowed to
 * repeat because MySQL permits multiple NULLs in a unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transcripts', function (Blueprint $table) {
            $table->dropIndex('idx_transcripts_user_local');
            $table->unique(['user_id', 'client_local_id'], 'unique_transcripts_user_client_local');
        });

        Schema::table('transcript_chunks', function (Blueprint $table) {
            $table->dropIndex('idx_chunks_transcript_local');
            $table->unique(['transcript_id', 'client_local_id'], 'unique_chunks_transcript_client_local');
        });

        Schema::table('summaries', function (Blueprint $table) {
            $table->dropIndex('idx_summaries_transcript_local');
            $table->unique(['transcript_id', 'client_local_id'], 'unique_summaries_transcript_client_local');
        });
    }

    public function down(): void
    {
        Schema::table('transcripts', function (Blueprint $table) {
            $table->dropUnique('unique_transcripts_user_client_local');
            $table->index(['user_id', 'client_local_id'], 'idx_transcripts_user_local');
        });

        Schema::table('transcript_chunks', function (Blueprint $table) {
            $table->dropUnique('unique_chunks_transcript_client_local');
            $table->index(['transcript_id', 'client_local_id'], 'idx_chunks_transcript_local');
        });

        Schema::table('summaries', function (Blueprint $table) {
            $table->dropUnique('unique_summaries_transcript_client_local');
            $table->index(['transcript_id', 'client_local_id'], 'idx_summaries_transcript_local');
        });
    }
};
