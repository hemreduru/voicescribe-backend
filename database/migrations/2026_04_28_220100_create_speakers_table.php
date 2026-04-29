<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speakers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('client_local_id', 100)->nullable();
            $table->string('remote_id', 100)->nullable();
            $table->string('name', 120);
            $table->longText('embedding')->nullable();
            $table->unsignedInteger('recordings')->default(0);
            $table->boolean('has_voice_sample')->default(false);
            $table->boolean('is_user_named')->default(false);
            $table->string('sync_status', 20)->default('synced');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('sync_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'sync_status'], 'idx_speakers_user_sync');
            $table->index(['user_id', 'client_local_id'], 'idx_speakers_user_local');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speakers');
    }
};

