<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Video timestamps: a comment anchored to a second of the video.
 *
 * Like image annotations, the comment stores the version number it was made
 * on (`annotated_version_number`), so a comment made on an older cut can be
 * flagged when the current version changes. `time_seconds` is the playhead
 * position, kept as a float so sub-second precision from the browser survives
 * the round trip; the UI renders it as mm:ss.
 *
 * A comment is therefore plain, spatial (`x`/`y`) or temporal
 * (`time_seconds`). The columns are independent: nothing forces a comment to
 * be both, and existing rows keep working untouched.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('photo_comments', function (Blueprint $table) {
            $table->decimal('time_seconds', 8, 2)->nullable()->after('annotated_version_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photo_comments', function (Blueprint $table) {
            $table->dropColumn('time_seconds');
        });
    }
};
