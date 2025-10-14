<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('stripe_test_account_id')->nullable()->after('stripe_account_id');
            $table->boolean('stripe_test_onboarded')->default(false)->after('stripe_onboarded');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('stripe_test_account_id');
            $table->dropColumn('stripe_test_onboarded');
        });
    }
};
