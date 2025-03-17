<?php

use App\Models\Photoshoot;
use App\Models\Team;
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
        Schema::create('galleries', function (Blueprint $table) {
            $table->id();
            $table->ulid()->index();
            $table->foreignIdFor(Team::class)->index();
            $table->foreignIdFor(Photoshoot::class)->nullable()->index();
            $table->string('name');
            $table->boolean('keep_original_size')->default(0);
            $table->boolean('is_shared')->default(0);
            $table->boolean('is_share_selectable')->default(0);
            $table->boolean('is_share_downloadable')->default(0);
            $table->boolean('is_share_watermarked')->default(0);
            $table->unsignedInteger('share_selection_limit')->nullable();
            $table->string('share_password')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('galleries');
    }
};
