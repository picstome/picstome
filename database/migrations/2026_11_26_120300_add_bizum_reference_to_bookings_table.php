<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Human-friendly Bizum reference, e.g. "Bea-27".
 *
 * It is stored because it is what the client sends with the transfer and the
 * photographer sees in the agenda and the emails.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('bizum_reference', 40)->nullable()->after('payment_method');
            $table->unique(['team_id', 'bizum_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'bizum_reference']);
            $table->dropColumn('bizum_reference');
        });
    }
};
