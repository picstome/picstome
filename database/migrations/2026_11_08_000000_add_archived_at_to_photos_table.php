<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archived photos stay on disk and in the row, but the photographer can hide
 * them from clients and from the gallery ZIP without deleting anything.
 *
 * A nullable timestamp mirrors `favorited_at`: null means "live", a value means
 * "archived at that moment". Nothing is backfilled, so every existing photo
 * starts live.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('favorited_at');
            $table->index(['gallery_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropIndex(['gallery_id', 'archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
