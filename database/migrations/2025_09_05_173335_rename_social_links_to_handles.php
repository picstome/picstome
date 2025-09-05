<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            // Rename columns from *_url to *_handle
            $table->renameColumn('instagram_url', 'instagram_handle');
            $table->renameColumn('youtube_url', 'youtube_handle');
            $table->renameColumn('facebook_url', 'facebook_handle');
            $table->renameColumn('x_url', 'x_handle');
            $table->renameColumn('tiktok_url', 'tiktok_handle');
            $table->renameColumn('twitch_url', 'twitch_handle');
            $table->renameColumn('website_url', 'website_url'); // Keep as is since it's already a URL
        });

        // Transform existing URL data to handles
        DB::table('teams')->whereNotNull('instagram_handle')->update([
            'instagram_handle' => DB::raw("REPLACE(REPLACE(instagram_handle, 'https://instagram.com/', ''), 'http://instagram.com/', '')")
        ]);

        DB::table('teams')->whereNotNull('youtube_handle')->update([
            'youtube_handle' => DB::raw("REPLACE(REPLACE(youtube_handle, 'https://youtube.com/', ''), 'http://youtube.com/', '')")
        ]);

        DB::table('teams')->whereNotNull('facebook_handle')->update([
            'facebook_handle' => DB::raw("REPLACE(REPLACE(facebook_handle, 'https://facebook.com/', ''), 'http://facebook.com/', '')")
        ]);

        DB::table('teams')->whereNotNull('x_handle')->update([
            'x_handle' => DB::raw("REPLACE(REPLACE(x_handle, 'https://x.com/', ''), 'http://x.com/', '')")
        ]);

        DB::table('teams')->whereNotNull('tiktok_handle')->update([
            'tiktok_handle' => DB::raw("REPLACE(REPLACE(REPLACE(tiktok_handle, 'https://tiktok.com/@', ''), 'http://tiktok.com/@', ''), '@', '')")
        ]);

        DB::table('teams')->whereNotNull('twitch_handle')->update([
            'twitch_handle' => DB::raw("REPLACE(REPLACE(twitch_handle, 'https://twitch.tv/', ''), 'http://twitch.tv/', '')")
        ]);
    }

    public function down(): void
    {
        // Transform handles back to URLs
        DB::table('teams')->whereNotNull('instagram_handle')->update([
            'instagram_handle' => DB::raw("CONCAT('https://instagram.com/', instagram_handle)")
        ]);

        DB::table('teams')->whereNotNull('youtube_handle')->update([
            'youtube_handle' => DB::raw("CONCAT('https://youtube.com/', youtube_handle)")
        ]);

        DB::table('teams')->whereNotNull('facebook_handle')->update([
            'facebook_handle' => DB::raw("CONCAT('https://facebook.com/', facebook_handle)")
        ]);

        DB::table('teams')->whereNotNull('x_handle')->update([
            'x_handle' => DB::raw("CONCAT('https://x.com/', x_handle)")
        ]);

        DB::table('teams')->whereNotNull('tiktok_handle')->update([
            'tiktok_handle' => DB::raw("CONCAT('https://tiktok.com/@', tiktok_handle)")
        ]);

        DB::table('teams')->whereNotNull('twitch_handle')->update([
            'twitch_handle' => DB::raw("CONCAT('https://twitch.tv/', twitch_handle)")
        ]);

        Schema::table('teams', function (Blueprint $table) {
            // Rename columns back from *_handle to *_url
            $table->renameColumn('instagram_handle', 'instagram_url');
            $table->renameColumn('youtube_handle', 'youtube_handle');
            $table->renameColumn('facebook_handle', 'facebook_url');
            $table->renameColumn('x_handle', 'x_url');
            $table->renameColumn('tiktok_handle', 'tiktok_url');
            $table->renameColumn('twitch_handle', 'twitch_url');
            $table->renameColumn('website_url', 'website_url'); // Keep as is
        });
    }
};
