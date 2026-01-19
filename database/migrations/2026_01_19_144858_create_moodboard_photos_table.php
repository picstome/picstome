<?php

use App\Models\Moodboard;
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
        Schema::create('moodboard_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Moodboard::class);
            $table->string('name');
            $table->string('path', 2048);
            $table->unsignedBigInteger('size');
            $table->string('disk')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moodboard_photos');
    }
};
