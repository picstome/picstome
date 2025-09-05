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
        Schema::table('teams', function (Blueprint $table) {
            $table->string('instagram_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('x_url')->nullable();
            $table->string('tiktok_url')->nullable();
            $table->string('twitch_url')->nullable();
            $table->string('website_url')->nullable();
            $table->json('other_social_links')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn([
                'instagram_url',
                'youtube_url',
                'facebook_url',
                'x_url',
                'tiktok_url',
                'twitch_url',
                'website_url',
                'other_social_links',
            ]);
        });
    }
};
