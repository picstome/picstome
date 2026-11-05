<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the focal point as a 0–1 ratio so the public cover crop keeps the
     * area the photographer selected, regardless of the rendered size.
     */
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->decimal('cover_focal_x', 5, 4)->default(0.5000)->after('cover_photo_id');
            $table->decimal('cover_focal_y', 5, 4)->default(0.5000)->after('cover_focal_x');
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn(['cover_focal_x', 'cover_focal_y']);
        });
    }
};
