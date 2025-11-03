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
        Schema::table('customers', function (Blueprint $table) {
            $table->index('team_id');
            $table->dropIndex(['team_id', 'email']);
            $table->unique(['team_id', 'email']);
            $table->dropIndex(['team_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->index('team_id');
            $table->dropUnique('customers_team_id_email_unique');
            $table->index(['team_id', 'email']);
            $table->dropIndex(['team_id']);
        });
    }
};
