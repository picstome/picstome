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
        Schema::table('photos', function (Blueprint $table) {
            $table->string('identity_key')->nullable()->after('source_name')->index();
        });

        DB::table('photos')
            ->select(['id', 'name', 'source_name'])
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
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn('identity_key');
        });
    }
};
