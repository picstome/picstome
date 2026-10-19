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
            $table->unsignedBigInteger('current_version_id')->nullable()->after('version_count')->index();
            $table->timestamp('uploaded_at')->nullable()->after('current_version_id');
        });

        Schema::table('photo_versions', function (Blueprint $table) {
            $table->timestamp('uploaded_at')->nullable()->after('status');
        });

        DB::table('photos')->update(['uploaded_at' => DB::raw('created_at')]);
        DB::table('photo_versions')->update(['uploaded_at' => DB::raw('created_at')]);

        DB::table('photos')
            ->select(['id', 'path'])
            ->whereNotNull('path')
            ->orderBy('id')
            ->each(function (object $photo): void {
                $versionId = DB::table('photo_versions')
                    ->where('photo_id', $photo->id)
                    ->where('path', $photo->path)
                    ->orderByDesc('version_number')
                    ->value('id');

                if ($versionId) {
                    DB::table('photos')
                        ->where('id', $photo->id)
                        ->update(['current_version_id' => $versionId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('photo_versions', function (Blueprint $table) {
            $table->dropColumn('uploaded_at');
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn(['current_version_id', 'uploaded_at']);
        });
    }
};
