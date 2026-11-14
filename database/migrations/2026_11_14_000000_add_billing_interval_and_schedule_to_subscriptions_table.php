<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the billing interval and current period end locally so the UI can
     * distinguish monthly and annual subscriptions and show the next renewal
     * without calling Stripe on every page. The scheduled columns keep a
     * monthly-to-annual change visible until Stripe applies it.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('billing_interval', 16)->nullable()->after('stripe_price');
            $table->string('stripe_schedule_id')->nullable()->after('billing_interval');
            $table->string('scheduled_tier', 16)->nullable()->after('stripe_schedule_id');
            $table->string('scheduled_price_id')->nullable()->after('scheduled_tier');
            $table->timestamp('scheduled_change_at')->nullable()->after('scheduled_price_id');
            $table->timestamp('current_period_end')->nullable()->after('scheduled_change_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'billing_interval',
                'stripe_schedule_id',
                'scheduled_tier',
                'scheduled_price_id',
                'scheduled_change_at',
                'current_period_end',
            ]);
        });
    }
};
