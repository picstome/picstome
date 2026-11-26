<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bizum settings for payment links.
 *
 * They live on the team instead of the booking settings so payment links keep
 * working when the booking feature flags are disabled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('bizum_enabled')->default(false)->after('show_pay_button');
            $table->string('bizum_phone', 32)->nullable()->after('bizum_enabled');
            $table->text('bizum_instructions')->nullable()->after('bizum_phone');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['bizum_enabled', 'bizum_phone', 'bizum_instructions']);
        });
    }
};
