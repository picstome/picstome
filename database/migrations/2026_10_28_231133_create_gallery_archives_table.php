<?php

use App\Models\Gallery;
use App\Models\Team;
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
        Schema::create('gallery_archives', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignIdFor(Gallery::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Team::class)->constrained()->cascadeOnDelete();
            $table->string('variant', 20)->default('all');
            $table->string('status', 20)->default('pending');
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->string('filename');
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('photos_total')->default(0);
            $table->unsignedInteger('photos_processed')->default(0);
            $table->unsignedInteger('photos_skipped')->default(0);
            $table->unsignedInteger('images_count')->default(0);
            $table->unsignedInteger('videos_count')->default(0);
            $table->unsignedInteger('pdfs_count')->default(0);
            $table->string('fingerprint', 64);
            $table->foreignIdFor(User::class, 'requested_by')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('downloads_count')->default(0);
            $table->timestamp('last_downloaded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamp('storage_deleted_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->unique(['gallery_id', 'variant', 'fingerprint']);
            $table->index(['team_id', 'status', 'expires_at']);
            $table->index(['gallery_id', 'variant', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gallery_archives');
    }
};
