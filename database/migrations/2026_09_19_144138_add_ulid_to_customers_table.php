<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->ulid()->nullable();
        });

        foreach (DB::table('customers')->whereNull('ulid')->orderBy('id')->get() as $customer) {
            DB::table('customers')->where('id', $customer->id)->update(['ulid' => (string) Str::ulid()]);
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->unique('ulid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['ulid']);
            $table->dropColumn('ulid');
        });
    }
};
