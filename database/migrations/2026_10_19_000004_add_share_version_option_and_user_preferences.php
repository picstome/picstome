<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->boolean('is_share_versions_visible')->default(false)->after('is_share_watermarked');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('preferences')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('preferences');
        });

        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn('is_share_versions_visible');
        });
    }
};
