<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Free plans moved from five contracts per month to one.
 *
 * New teams read the limit from config at registration and when a subscription
 * ends, but teams created before this change keep the old default on their row.
 * Only teams still on the old default (5) without a lifetime purchase or a
 * valid subscription are updated, so paid teams and admin-customised limits
 * are left untouched.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('teams')
            ->where('monthly_contract_limit', 5)
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
            ->update(['monthly_contract_limit' => 1]);
    }

    /**
     * Reverse the migrations.
     *
     * The previous value cannot be derived safely because some teams may have
     * had a custom limit, so rollback keeps the applied policy.
     */
    public function down(): void
    {
        //
    }
};
