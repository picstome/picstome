<?php

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
        Schema::create('moodboards', function (Blueprint $table) {
            $table->id();
            $table->ulid()->index();
            $table->foreignIdFor(\App\Models\Team::class)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('cover_photo_id')->nullable()->constrained('photos')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moodboards');
    }
};
