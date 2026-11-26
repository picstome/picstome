<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the public booking page needs: which payment methods are offered,
 * whether the phone is mandatory and the job created once a booking is paid.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('booking_settings', function (Blueprint $table) {
            $table->boolean('phone_required')->default(false)->after('horizon_days');
            $table->boolean('card_enabled')->default(true)->after('bizum_enabled');
        });

        Schema::table('bookings', function (Blueprint $table) {
            // The photoshoot is created only when the booking is paid, so the
            // rest of the app (galleries, contracts, invoices) can attach to it.
            $table->foreignId('photoshoot_id')
                ->nullable()
                ->after('customer_id')
                ->constrained()
                ->nullOnDelete();

            // What the client pays now: the full price or the deposit.
            $table->string('payment_kind', 16)->nullable()->after('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['photoshoot_id']);
            $table->dropColumn(['photoshoot_id', 'payment_kind']);
        });

        Schema::table('booking_settings', function (Blueprint $table) {
            $table->dropColumn(['phone_required', 'card_enabled']);
        });
    }
};
