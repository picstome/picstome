<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('photos', 'stored_hash')) {
            Schema::table('photos', function (Blueprint $table): void {
                $table->string('stored_hash', 64)->nullable()->after('hash');
            });
        }

        if (! Schema::hasColumn('photo_versions', 'stored_hash')) {
            Schema::table('photo_versions', function (Blueprint $table): void {
                $table->string('stored_hash', 64)->nullable()->after('stored_size');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('photos', 'stored_hash')) {
            Schema::table('photos', function (Blueprint $table): void {
                $table->dropColumn('stored_hash');
            });
        }

        if (Schema::hasColumn('photo_versions', 'stored_hash')) {
            Schema::table('photo_versions', function (Blueprint $table): void {
                $table->dropColumn('stored_hash');
            });
        }
    }
};
