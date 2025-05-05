<?php

use App\Models\User;
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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->index();
            $table->string('name');
            $table->boolean('personal_team');
            $table->string('brand_logo_path')->nullable();
            $table->string('brand_logo_icon_path')->nullable();
            $table->string('brand_watermark_path')->nullable();
            $table->string('brand_watermark_position')->nullable();
            $table->string('brand_color')->nullable();
            $table->string('brand_font')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
