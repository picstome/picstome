<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Passwordless accounts store no password until the user sets one.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Restore the NOT NULL constraint, filling passwordless accounts with
     * unusable random hashes first so the rollback does not fail.
     */
    public function down(): void
    {
        DB::table('users')
            ->whereNull('password')
            ->update(['password' => Hash::make(Str::random(40))]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
