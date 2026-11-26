<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Some photographers want the invoice of a collected payment issued and
 * numbered immediately instead of reviewed as a draft.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('booking_settings', function (Blueprint $table) {
            $table->boolean('auto_issue_invoices')->default(false)->after('cancellation_policy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_settings', function (Blueprint $table) {
            $table->dropColumn('auto_issue_invoices');
        });
    }
};
