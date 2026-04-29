<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->string('client_local_id', 100)->nullable()->after('entity_id');
            $table->string('remote_id', 100)->nullable()->after('client_local_id');
            $table->unsignedInteger('retry_count')->default(0)->after('status_id');
            $table->json('meta')->nullable()->after('error_message');
            $table->timestamp('last_synced_at')->nullable()->after('synced_at');
            $table->softDeletes();

            $table->index(['user_id', 'retry_count'], 'idx_sync_logs_user_retry');
        });
    }

    public function down(): void
    {
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->dropIndex('idx_sync_logs_user_retry');
            $table->dropSoftDeletes();
            $table->dropColumn([
                'client_local_id',
                'remote_id',
                'retry_count',
                'meta',
                'last_synced_at',
            ]);
        });
    }
};

