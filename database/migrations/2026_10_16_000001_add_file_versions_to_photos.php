<?php

use App\Services\PhotoIdentityService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addPhotoColumns();
        $this->createPhotoVersionsTable();
        $this->addGalleryColumns();
        $this->addUserColumns();
        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_versions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('preferences');
        });

        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn('is_share_versions_visible');
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn([
                'source_name',
                'version_count',
                'current_version_id',
                'uploaded_at',
                'identity_key',
                'stored_size',
            ]);
        });
    }

    protected function addPhotoColumns(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            if (! Schema::hasColumn('photos', 'source_name')) {
                $table->string('source_name')->nullable()->after('name');
            }

            if (! Schema::hasColumn('photos', 'version_count')) {
                $table->unsignedInteger('version_count')->default(1)->after('source_name');
            }

            if (! Schema::hasColumn('photos', 'current_version_id')) {
                $table->unsignedBigInteger('current_version_id')->nullable()->index()->after('version_count');
            }

            if (! Schema::hasColumn('photos', 'uploaded_at')) {
                $table->timestamp('uploaded_at')->nullable()->after('current_version_id');
            }

            if (! Schema::hasColumn('photos', 'identity_key')) {
                $table->string('identity_key')->nullable()->index()->after('source_name');
            }

            if (! Schema::hasColumn('photos', 'stored_size')) {
                $table->unsignedBigInteger('stored_size')->default(0)->after('size');
            }
        });
    }

    protected function createPhotoVersionsTable(): void
    {
        if (! Schema::hasTable('photo_versions')) {
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
                $table->unsignedBigInteger('stored_size')->default(0);
                $table->string('status', 20)->default('pending');
                $table->timestamp('uploaded_at')->nullable();
                $table->timestamps();

                $table->unique(['photo_id', 'version_number']);
                $table->index('photo_id');
            });

            return;
        }

        Schema::table('photo_versions', function (Blueprint $table) {
            if (! Schema::hasColumn('photo_versions', 'source_name')) {
                $table->string('source_name')->nullable()->after('name');
            }

            if (! Schema::hasColumn('photo_versions', 'stored_size')) {
                $table->unsignedBigInteger('stored_size')->default(0)->after('size');
            }

            if (! Schema::hasColumn('photo_versions', 'uploaded_at')) {
                $table->timestamp('uploaded_at')->nullable()->after('status');
            }
        });
    }

    protected function addGalleryColumns(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            if (! Schema::hasColumn('galleries', 'is_share_versions_visible')) {
                $table->boolean('is_share_versions_visible')->default(false)->after('is_share_watermarked');
            }
        });
    }

    protected function addUserColumns(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'preferences')) {
                $table->json('preferences')->nullable()->after('password');
            }
        });
    }

    protected function backfill(): void
    {
        DB::table('photos')->whereNull('source_name')->update(['source_name' => DB::raw('name')]);
        DB::table('photos')->where('version_count', 0)->update(['version_count' => 1]);
        DB::table('photos')->whereNull('uploaded_at')->update(['uploaded_at' => DB::raw('created_at')]);
        DB::table('photo_versions')->whereNull('uploaded_at')->update(['uploaded_at' => DB::raw('created_at')]);
        DB::table('photos')->where('stored_size', 0)->update(['stored_size' => DB::raw('size')]);
        DB::table('photo_versions')->where('stored_size', 0)->update(['stored_size' => DB::raw('size')]);

        DB::table('photos')
            ->select(['id', 'name', 'source_name'])
            ->whereNull('identity_key')
            ->orderBy('id')
            ->chunkById(500, function ($photos): void {
                foreach ($photos as $photo) {
                    DB::table('photos')
                        ->where('id', $photo->id)
                        ->update([
                            'identity_key' => PhotoIdentityService::key($photo->source_name ?: $photo->name),
                        ]);
                }
            });

        DB::table('photos')
            ->select(['id', 'path'])
            ->whereNotNull('path')
            ->orderBy('id')
            ->chunkById(500, function ($photos): void {
                foreach ($photos as $photo) {
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
                }
            });
    }
};
