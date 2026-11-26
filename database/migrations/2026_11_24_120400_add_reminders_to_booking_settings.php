<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two configurable reminders per team, sent before a confirmed session.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('booking_settings', function (Blueprint $table) {
            $table->boolean('reminder_first_enabled')->default(true)->after('auto_issue_invoices');
            $table->unsignedSmallInteger('reminder_first_hours')->default(72)->after('reminder_first_enabled');
            $table->boolean('reminder_second_enabled')->default(true)->after('reminder_first_hours');
            $table->unsignedSmallInteger('reminder_second_hours')->default(24)->after('reminder_second_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_settings', function (Blueprint $table) {
            $table->dropColumn([
                'reminder_first_enabled',
                'reminder_first_hours',
                'reminder_second_enabled',
                'reminder_second_hours',
            ]);
        });
    }
};
