<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content metadata exposed by the external API manifest. Existing photos keep
 * null values; the API reports them as unknown instead of hashing or decoding
 * every file on the fly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->string('hash', 64)->nullable()->after('stored_size');
            $table->string('hash_algo', 16)->nullable()->after('hash');
            $table->unsignedInteger('width')->nullable()->after('hash_algo');
            $table->unsignedInteger('height')->nullable()->after('width');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn(['hash', 'hash_algo', 'width', 'height']);
        });
    }
};
