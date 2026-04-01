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
            $table->string('speaker_label', 100)->nullable();
            $table->decimal('start_time', 10, 3)->comment('seconds');
            $table->decimal('end_time', 10, 3)->comment('seconds');
            $table->decimal('confidence', 3, 2)->nullable();
            $table->timestamps();

            $table->index(['transcript_id', 'start_time'], 'idx_transcript_time');
            $table->index(['transcript_id', 'chunk_index'], 'idx_transcript_chunk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_chunks');
    }
};
