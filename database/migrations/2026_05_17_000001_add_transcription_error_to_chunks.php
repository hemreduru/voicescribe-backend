<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transcript_chunks', function (Blueprint $table) {
            $table->text('transcription_error')->nullable()->after('confidence');
        });
    }

    public function down(): void
    {
        Schema::table('transcript_chunks', function (Blueprint $table) {
            $table->dropColumn('transcription_error');
        });
    }
};
