<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link payments to the booking they settle.
 *
 * `payment_kind` tells a full payment from a deposit and its later balance,
 * which is what allows one invoice per payment. Payment-link payments keep
 * both columns null and are unaffected.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('booking_id')
                ->nullable()
                ->after('photoshoot_id')
                ->constrained('bookings')
                ->nullOnDelete();

            // full | deposit | balance. Null for payment-link payments.
            $table->string('payment_kind', 16)->nullable()->after('description');

            $table->index(['booking_id', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropIndex(['booking_id', 'completed_at']);
            $table->dropColumn(['booking_id', 'payment_kind']);
        });
    }
};
