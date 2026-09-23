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
        DB::statement("UPDATE galleries SET share_password = NULL WHERE share_password IS NOT NULL AND TRIM(share_password) = ''");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
