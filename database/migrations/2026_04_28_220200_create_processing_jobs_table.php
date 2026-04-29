<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processing_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transcript_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('client_local_id', 100)->nullable();
            $table->string('remote_id', 100)->nullable();
            $table->string('type', 50);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('last_processed_chunk_index')->default(0);
            $table->unsignedInteger('retry_count')->default(0);
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->string('sync_status', 20)->default('synced');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('sync_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status'], 'idx_jobs_user_status');
            $table->index(['user_id', 'sync_status'], 'idx_jobs_user_sync');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processing_jobs');
    }
};

