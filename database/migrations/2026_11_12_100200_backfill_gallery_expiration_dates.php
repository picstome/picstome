<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Give a date to galleries that were created before gallery retention existed.
 *
 * Free and lifetime galleries expire one year after creation. Private galleries
 * without a date would otherwise live forever, so they get one year from
 * creation, or a month of grace when that is already in the past.
 *
 * Public galleries are skipped on purpose: they are the portfolio and do not
 * expire. Galleries with a date are never touched: an owner may already have
 * chosen a shorter one.
 *
 * The update runs in PHP instead of a date function so the same migration works
 * on MySQL and on the SQLite database used by the test suite.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $oneYearAgo = now()->subYear();
        $graceDate = now()->addMonth()->toDateString();

        DB::table('galleries')
            ->whereNull('expiration_date')
            ->where('is_public', false)
            ->orderBy('id')
            ->chunkById(200, function ($galleries) use ($oneYearAgo, $graceDate): void {
                foreach ($galleries as $gallery) {
                    $createdAt = Carbon::parse($gallery->created_at);

                    $expiration = $createdAt->gt($oneYearAgo)
                        ? $createdAt->copy()->addYear()->toDateString()
                        : $graceDate;

                    DB::table('galleries')
                        ->where('id', $gallery->id)
                        ->update(['expiration_date' => $expiration]);
                }
            }, 'id');
    }

    /**
     * Reverse the migrations.
     *
     * The previous dates cannot be derived safely, so rollback keeps the
     * applied policy instead of leaving galleries that never expire.
     */
    public function down(): void
    {
        //
    }
};
