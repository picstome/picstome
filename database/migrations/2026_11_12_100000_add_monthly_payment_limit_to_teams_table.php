<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Optional per-team monthly payment limit.
 *
 * It mirrors the monthly invoice and contract limits: null means unlimited, so
 * existing free teams are backfilled with the one-payment-per-month plan
 * default. Paid and lifetime teams keep unlimited.
 *
 * Only completed payments consume the limit, so an abandoned Stripe checkout
 * never costs a free photographer their monthly payment.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->integer('monthly_payment_limit')->nullable()->after('monthly_invoice_limit');
        });

        DB::table('teams')
            ->whereNull('monthly_payment_limit')
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
            ->update(['monthly_payment_limit' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('monthly_payment_limit');
        });
    }
};
