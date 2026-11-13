<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Camera uploads pause when the team has no storage left.
 *
 * The ingest job used to leave rejected files in the inbox forever, so they
 * were stored without being billed or shown. These flags let the ingestor stop
 * scanning the inbox and notify the photographer once per pause instead of once
 * per discarded file.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ftp_accounts', function (Blueprint $table) {
            $table->timestamp('quota_paused_at')->nullable()->after('last_upload_at');
            $table->timestamp('quota_paused_notified_at')->nullable()->after('quota_paused_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ftp_accounts', function (Blueprint $table) {
            $table->dropColumn(['quota_paused_at', 'quota_paused_notified_at']);
        });
    }
};
