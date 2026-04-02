<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcript_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name_en', 100);
            $table->string('name_tr', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('llm_providers', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name_en', 100);
            $table->string('name_tr', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sync_actions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name_en', 100);
            $table->string('name_tr', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sync_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name_en', 100);
            $table->string('name_tr', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_statuses');
        Schema::dropIfExists('sync_actions');
        Schema::dropIfExists('llm_providers');
        Schema::dropIfExists('transcript_statuses');
    }
};
