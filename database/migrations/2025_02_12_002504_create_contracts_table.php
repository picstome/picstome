<?php

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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->ulid()->index();
            $table->foreignIdFor(Team::class)->index();
            $table->foreignId('photoshoot_id')->nullable();
            $table->string('title');
            $table->string('description');
            $table->string('location');
            $table->dateTime('shooting_date');
            $table->text('markdown_body');
            $table->dateTime('executed_at')->nullable();
            $table->string('pdf_file_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
