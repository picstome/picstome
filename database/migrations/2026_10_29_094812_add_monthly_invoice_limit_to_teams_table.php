<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Optional per-team monthly invoice limit.
 *
 * It mirrors the monthly contract limit: null means unlimited, so existing
 * free teams are backfilled with the one-invoice-per-month plan default they
 * had before this column existed. Paid and lifetime teams keep unlimited.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->integer('monthly_invoice_limit')->nullable()->after('monthly_contract_limit');
        });

        DB::table('teams')
            ->whereNull('monthly_invoice_limit')
            ->whereNull('lifetime_at')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('subscriptions')
                    ->whereColumn('subscriptions.team_id', 'teams.id')
                    ->where(function ($query) {
                        $query->whereNull('subscriptions.ends_at')
                            ->orWhere('subscriptions.ends_at', '>', now());
                    });
            })
            ->update(['monthly_invoice_limit' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('monthly_invoice_limit');
        });
    }
};
