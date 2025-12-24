<?php

use App\Models\Moodboard;
use App\Models\Photo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moodboard_photo', function (Blueprint $table) {
            $table->foreignIdFor(Moodboard::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Photo::class)->constrained()->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->primary(['moodboard_id', 'photo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moodboard_photo');
    }
};
