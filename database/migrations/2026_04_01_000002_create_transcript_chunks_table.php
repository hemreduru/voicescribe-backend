<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcript_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transcript_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('text');
            $table->decimal('start_time', 10, 3)->comment('seconds');
            $table->decimal('end_time', 10, 3)->comment('seconds');
            $table->decimal('confidence', 3, 2)->nullable();
            $table->string('client_local_id', 100)->nullable();
            $table->string('remote_id', 100)->nullable();
            $table->string('sync_status', 20)->default('synced');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('sync_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['transcript_id', 'start_time'], 'idx_transcript_time');
            $table->index(['transcript_id', 'chunk_index'], 'idx_transcript_chunk');
            $table->index(['transcript_id', 'sync_status'], 'idx_chunks_transcript_sync');
            $table->index(['transcript_id', 'client_local_id'], 'idx_chunks_transcript_local');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_chunks');
    }
};
