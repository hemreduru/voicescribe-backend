<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chat over the user's own transcriptions: sessions + messages, plus a FULLTEXT
 * index on transcript chunks to power retrieval (the queryable knowledge pool).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16); // 'user' | 'assistant'
            $table->text('content');
            // Source attribution for assistant messages: [{transcript_id,title,date}]
            $table->json('sources')->nullable();
            $table->timestamps();
            $table->index(['chat_session_id', 'id']);
        });

        // FULLTEXT index for retrieving relevant transcript chunks per query.
        // Wrapped in try/catch so non-MySQL (e.g. sqlite test) migrations still run.
        try {
            DB::statement('ALTER TABLE transcript_chunks ADD FULLTEXT chunks_text_fulltext (text)');
        } catch (\Throwable $e) {
            // FULLTEXT unsupported on this driver (e.g. sqlite) — retrieval falls
            // back to LIKE matching in TranscriptRetriever.
        }
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE transcript_chunks DROP INDEX chunks_text_fulltext');
        } catch (\Throwable $e) {
            // ignore
        }
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_sessions');
    }
};
