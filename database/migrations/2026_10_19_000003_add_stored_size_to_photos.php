<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->unsignedBigInteger('stored_size')->default(0)->after('size');
        });

        Schema::table('photo_versions', function (Blueprint $table) {
            $table->unsignedBigInteger('stored_size')->default(0)->after('size');
        });

        DB::table('photos')->update(['stored_size' => DB::raw('size')]);
        DB::table('photo_versions')->update(['stored_size' => DB::raw('size')]);
    }

    public function down(): void
    {
        Schema::table('photo_versions', function (Blueprint $table) {
            $table->dropColumn('stored_size');
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn('stored_size');
        });
    }
};
