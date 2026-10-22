<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link a payment with the invoice that settles it.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('invoice_id')
                ->nullable()
                ->after('photoshoot_id')
                ->constrained('invoices')
                ->nullOnDelete();

            $table->index(['invoice_id', 'completed_at']);
        });
    }

    /**
     * Reverse the invoicing link of the payments.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropIndex(['invoice_id', 'completed_at']);
            $table->dropColumn('invoice_id');
        });
    }
};
