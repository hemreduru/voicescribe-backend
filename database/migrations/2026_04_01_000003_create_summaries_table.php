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
            $table->foreignId('provider_id')
                ->constrained('llm_providers')
                ->restrictOnDelete();
            $table->string('model', 100);
            $table->text('summary_text');
            $table->unsignedInteger('token_count')->nullable();
            $table->unsignedInteger('processing_time_ms')->nullable();
            $table->timestamps();

            $table->index('provider_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summaries');
    }
};
