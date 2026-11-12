<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the "is this file still referenced?" lookup.
 *
 * `DeleteFromDisk` runs two `exists()` queries per deleted file, filtering on
 * `disk` plus `path`/`raw_path`/`thumb_path`. None of those columns had an
 * index, so every check was a full table scan: p95 1.65s, observed at 1.9K
 * calls over two weeks, mostly from the nightly expired-gallery purge which
 * dispatches up to three jobs per photo and per version.
 *
 * The path columns are `string(2048)`. With utf8mb4 that is 8192 bytes per
 * value, well over InnoDB's 3072 byte index limit, so MySQL needs a prefix
 * index. SQLite has no prefix syntax, so the two drivers get different DDL.
 *
 * A 191 character prefix is enough in practice: real object keys look like
 * `teams/123/galleries/01J.../photos/01J...-name.jpg`, and the ulids that make
 * them unique sit well inside the first 191 characters.
 */
return new class extends Migration
{
    /**
     * Columns searched by `DeleteFromDisk::isStillReferenced()`.
     *
     * @var list<string>
     */
    private array $pathColumns = ['path', 'raw_path', 'thumb_path'];

    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        foreach (['photos', 'photo_versions'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $driver): void {
                foreach ($this->pathColumns as $column) {
                    $name = "{$table}_{$column}_index";

                    if ($driver === 'mysql') {
                        // Prefix index: see the class docblock for the byte limit.
                        $blueprint->rawIndex("`{$column}`(191)", $name);
                    } else {
                        $blueprint->index($column, $name);
                    }
                }

                // The lookup always constrains on disk, so pair it with the
                // most common path column to give the optimizer a covering
                // starting point. The path column needs the same prefix as
                // above: `disk` (50 chars) + `path` (2048 chars) is 8392 bytes
                // in utf8mb4, which MySQL rejects with error 1071.
                if ($driver === 'mysql') {
                    $blueprint->rawIndex('`disk`, `path`(191)', "{$table}_disk_path_index");
                } else {
                    $blueprint->index(['disk', 'path'], "{$table}_disk_path_index");
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['photos', 'photo_versions'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropIndex("{$table}_disk_path_index");

                foreach ($this->pathColumns as $column) {
                    $blueprint->dropIndex("{$table}_{$column}_index");
                }
            });
        }
    }
};
