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
            $table->string('local_id', 36)->comment('UUID from mobile app');
            $table->string('title')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->foreignId('status_id')
                ->constrained('transcript_statuses')
                ->restrictOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'local_id'], 'unique_local_id');
            $table->index('status_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcripts');
    }
};
