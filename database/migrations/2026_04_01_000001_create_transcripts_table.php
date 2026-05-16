<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('local_id', 100)->nullable()->comment('Legacy mobile local ID');
            $table->string('client_local_id', 100)->nullable();
            $table->string('remote_id', 100)->nullable();
            $table->string('title')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->foreignId('status_id')
                ->constrained('transcript_statuses')
                ->restrictOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->string('sync_status', 20)->default('synced');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('sync_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'local_id'], 'unique_local_id');
            $table->index('status_id');
            $table->index(['user_id', 'sync_status'], 'idx_transcripts_user_sync');
            $table->index(['user_id', 'client_local_id'], 'idx_transcripts_user_local');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcripts');
    }
};
