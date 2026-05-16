<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transcript_id')->constrained()->cascadeOnDelete();
            $table->string('client_local_id', 100)->nullable();
            $table->string('remote_id', 100)->nullable();
            $table->foreignId('provider_id')
                ->constrained('llm_providers')
                ->restrictOnDelete();
            $table->string('model', 100);
            $table->text('summary_text');
            $table->unsignedInteger('token_count')->nullable();
            $table->unsignedInteger('processing_time_ms')->nullable();
            $table->string('sync_status', 20)->default('synced');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('sync_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('provider_id');
            $table->index(['transcript_id', 'sync_status'], 'idx_summaries_transcript_sync');
            $table->index(['transcript_id', 'client_local_id'], 'idx_summaries_transcript_local');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summaries');
    }
};
