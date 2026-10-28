<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identify the single gallery the FTP ingest creates for a team and day
     * when no live session is receiving uploads. The date, not the localized
     * name, is the stable identity, so web and queue workers agree.
     */
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->date('daily_shoot_date')->nullable()->after('is_public');
            $table->unique(['team_id', 'daily_shoot_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'daily_shoot_date']);
            $table->dropColumn('daily_shoot_date');
        });
    }
};
