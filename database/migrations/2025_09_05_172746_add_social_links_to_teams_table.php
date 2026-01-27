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
            $table->string('instagram_handle')->nullable();
            $table->string('youtube_handle')->nullable();
            $table->string('facebook_handle')->nullable();
            $table->string('x_handle')->nullable();
            $table->string('tiktok_handle')->nullable();
            $table->string('twitch_handle')->nullable();
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
                'instagram_handle',
                'youtube_handle',
                'facebook_handle',
                'x_handle',
                'tiktok_handle',
                'twitch_handle',
                'website_url',
                'other_social_links',
            ]);
        });
    }
};
