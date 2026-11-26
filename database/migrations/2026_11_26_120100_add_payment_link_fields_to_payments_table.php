<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pending payment-link checkouts.
 *
 * Card payments are still created only when Stripe confirms them. A Bizum
 * transfer is confirmed by hand, so the request has to exist before the money
 * arrives: status, reference, expiry and the checkout metadata live here until
 * the photographer confirms or cancels it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('status', 20)->default('completed')->after('completed_at');
            $table->string('payment_method', 16)->nullable()->after('status');
            $table->string('reference', 32)->nullable()->unique()->after('payment_method');
            $table->timestamp('expires_at')->nullable()->after('reference');
            $table->json('metadata')->nullable()->after('expires_at');
            $table->string('customer_name')->nullable()->after('customer_email');
            $table->string('customer_phone', 50)->nullable()->after('customer_name');

            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['status', 'expires_at']);
            $table->dropUnique(['reference']);
            $table->dropColumn([
                'status',
                'payment_method',
                'reference',
                'expires_at',
                'metadata',
                'customer_name',
                'customer_phone',
            ]);
        });
    }
};
