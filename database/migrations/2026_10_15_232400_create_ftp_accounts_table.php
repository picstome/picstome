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
        Schema::create('ftp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('username')->unique();
            $table->text('password');
            $table->boolean('active')->default(false);
            $table->timestamp('last_upload_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ftp_accounts');
    }
};
