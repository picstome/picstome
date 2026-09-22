<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("UPDATE customers SET email = NULL WHERE email IS NOT NULL AND TRIM(email) = ''");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
