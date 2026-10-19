<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->string('source_name')->nullable()->after('name');
            $table->unsignedInteger('version_count')->default(1)->after('source_name');
        });

        Schema::create('photo_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_id')->constrained('photos')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('name');
            $table->string('source_name')->nullable();
            $table->string('path', 2048);
            $table->string('thumb_path', 2048)->nullable();
            $table->string('raw_path', 2048)->nullable();
            $table->string('disk', 50)->default('s3');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(['photo_id', 'version_number']);
            $table->index('photo_id');
        });

        DB::table('photos')->update([
            'source_name' => DB::raw('name'),
            'version_count' => 1,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_versions');

        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn(['source_name', 'version_count']);
        });
    }
};
