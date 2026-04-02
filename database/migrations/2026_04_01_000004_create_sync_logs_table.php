<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('action_id')
                ->constrained('sync_actions')
                ->restrictOnDelete();
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->foreignId('status_id')
                ->constrained('sync_statuses')
                ->restrictOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status_id'], 'idx_user_status');
            $table->index(['entity_type', 'entity_id'], 'idx_entity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
