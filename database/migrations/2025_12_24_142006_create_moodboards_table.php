<?php

use App\Models\Photoshoot;
use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moodboards', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Team::class)->index();
            $table->foreignIdFor(Photoshoot::class)->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moodboards');
    }
};
