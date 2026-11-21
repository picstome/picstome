<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes for the two foreign keys the contract screens count and list by.
     */
    public function up(): void
    {
        Schema::table('signatures', function (Blueprint $table) {
            $table->index('contract_id');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->index('photoshoot_id');
        });
    }

    public function down(): void
    {
        Schema::table('signatures', function (Blueprint $table) {
            $table->dropIndex(['contract_id']);
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex(['photoshoot_id']);
        });
    }
};
