<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->timestamp('storage_limit_notification_sent_at')->nullable()->after('custom_storage_limit');
            $table->timestamp('storage_alert_dismissed_at')->nullable()->after('storage_limit_notification_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn([
                'storage_limit_notification_sent_at',
                'storage_alert_dismissed_at',
            ]);
        });
    }
};
