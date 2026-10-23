<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photo annotations: a comment pinned to a position on the image.
 *
 * Coordinates are stored as percentages (0-100) of the rendered image box so
 * they remain valid across thumbnails, responsive layouts and zoom states.
 *
 * The version number is stored instead of a photo_version_id foreign key on
 * purpose: version rows can be deleted (including `photos:collapse-versions`),
 * which would null the reference and make an old annotation look current. The
 * number stays readable and lets the UI flag annotations made on another
 * version. A null version means a plain comment (or a legacy one).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->boolean('are_annotations_enabled')->default(false)->after('are_comments_enabled');
        });

        Schema::table('photo_comments', function (Blueprint $table) {
            $table->decimal('x', 5, 2)->nullable()->after('comment');
            $table->decimal('y', 5, 2)->nullable()->after('x');
            $table->unsignedSmallInteger('annotated_version_number')->nullable()->after('y');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photo_comments', function (Blueprint $table) {
            $table->dropColumn(['x', 'y', 'annotated_version_number']);
        });

        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn('are_annotations_enabled');
        });
    }
};
