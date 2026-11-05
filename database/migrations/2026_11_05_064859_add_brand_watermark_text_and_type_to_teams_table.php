<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the watermark type, text, font and size columns.
     *
     * Existing teams default to the "image" type so their uploaded watermark
     * keeps working without a data backfill.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('brand_watermark_type')->default('image')->after('brand_watermark_path');
            $table->string('brand_watermark_text')->nullable()->after('brand_watermark_type');
            $table->string('brand_watermark_font')->nullable()->after('brand_watermark_text');
            $table->string('brand_watermark_size')->nullable()->after('brand_watermark_font');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn([
                'brand_watermark_size',
                'brand_watermark_font',
                'brand_watermark_text',
                'brand_watermark_type',
            ]);
        });
    }
};
