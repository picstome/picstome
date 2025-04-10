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
        Schema::create('photoshoots', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Team::class)->index();
            $table->string('name');
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->date('date')->nullable();
            $table->integer('price')->nullable();
            $table->string('location')->nullable();
            $table->string('comment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photoshoots');
    }
};
