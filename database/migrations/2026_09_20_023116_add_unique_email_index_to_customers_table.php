<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL treats '' as a value in a unique index, unlike NULL, and
        // teams carry multiple empty-string emails, so normalize first.
        DB::statement("UPDATE customers SET email = NULL WHERE email IS NOT NULL AND TRIM(email) = ''");

        // MySQL keeps the customers.team_id foreign key on the plain index,
        // so the unique index must exist before the plain one is dropped.
        Schema::table('customers', function (Blueprint $table) {
            $table->unique(['team_id', 'email']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'email']);
            $table->index(['team_id', 'email']);
        });
    }
};
