<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photographer-only star rating (1-5).
 *
 * Mirrors `favorited_at`: null means "not rated". Ratings are internal to the
 * team and are never exposed to gallery clients; clients keep favouriting by
 * heart only. The composite indexes cover the owner grid filters and sorts
 * (rating, upload date and file size) without hydrating rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable()->after('favorited_at');

            $table->index(['gallery_id', 'rating']);
            $table->index(['gallery_id', 'archived_at', 'uploaded_at']);
            $table->index(['gallery_id', 'archived_at', 'size']);
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropIndex(['gallery_id', 'rating']);
            $table->dropIndex(['gallery_id', 'archived_at', 'uploaded_at']);
            $table->dropIndex(['gallery_id', 'archived_at', 'size']);

            $table->dropColumn('rating');
        });
    }
};
