<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the full invoice number printed to the client and the manual paid
     * flag used only for the internal record of the team.
     *
     * Existing issued invoices keep the number they were printed with, so the
     * column is backfilled before the unique index is created.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('full_number', 100)->nullable()->after('number');
            $table->timestamp('paid_at')->nullable()->after('status');
        });

        DB::table('invoices')
            ->whereNotNull('series')
            ->whereNotNull('year')
            ->whereNotNull('number')
            ->whereNull('full_number')
            ->chunkById(100, function ($invoices): void {
                foreach ($invoices as $invoice) {
                    DB::table('invoices')
                        ->where('id', $invoice->id)
                        ->update([
                            'full_number' => sprintf(
                                '%s-%d-%s',
                                $invoice->series,
                                $invoice->year,
                                str_pad((string) $invoice->number, 4, '0', STR_PAD_LEFT)
                            ),
                        ]);
                }
            });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unique(['team_id', 'full_number']);
        });
    }

    /**
     * Reverse the schema changes.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'full_number']);
            $table->dropColumn(['full_number', 'paid_at']);
        });
    }
};
