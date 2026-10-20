<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculator_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained()->onDelete('cascade');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->json('state');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculator_states');
    }
};
