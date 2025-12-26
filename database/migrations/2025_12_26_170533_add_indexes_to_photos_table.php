<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->index('gallery_id');
            $table->index('favorited_at');
            $table->index(['gallery_id', 'favorited_at']);
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropIndex(['gallery_id', 'favorited_at']);
            $table->dropIndex('favorited_at');
            $table->dropIndex('gallery_id');
        });
    }
};
