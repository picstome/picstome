<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-time browser authorizations for external clients (Picstome Sync and
 * future apps). Only the SHA-256 hash of the code is stored so a database
 * leak cannot be used to exchange an authorization.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('client_name', 64);
            $table->string('code_hash', 64)->unique();
            $table->string('nonce', 128);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'consumed_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_authorizations');
    }
};
