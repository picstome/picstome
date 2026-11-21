<?php

namespace App\Traits\Archives;

use App\Jobs\DeleteGalleryArchive;
use App\Models\Gallery;
use App\Models\GalleryArchive;
use App\Models\Photo;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use Throwable;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

/**
 * Streams gallery files into a ZIP archive, sequentially or with a bounded
 * parallel S3 download window.
 */
trait BuildsArchiveZip
{
    public function build(GalleryArchive $archive): void
    {
        $archive->refresh();

        if (! in_array($archive->status, ['pending', 'building', 'failed'], true) || $archive->isExpired()) {
            return;
        }

        if ($archive->path) {
            DeleteGalleryArchive::dispatch($archive->id, $archive->path, $archive->disk ?: $this->disk());
        }

        $gallery = $archive->gallery;

        if (! $gallery) {
            $this->markFailed($archive, 'The gallery no longer exists.');

            return;
        }

        $archive->update([
            'status' => 'building',
            'started_at' => now(),
            'completed_at' => null,
            'expires_at' => null,
            'failure_reason' => null,
            'path' => null,
            'size_bytes' => null,
            'photos_total' => 0,
            'photos_processed' => 0,
            'photos_skipped' => 0,
            'images_count' => 0,
            'videos_count' => 0,
            'pdfs_count' => 0,
        ]);

        $query = $this->photosQuery($gallery, $archive->variant);
        $archive->update(['photos_total' => $query->count()]);

        $maxSize = (int) config('picstome.gallery_archives.max_size_bytes', 0);
        $estimate = $this->estimate($gallery, $archive->variant);

        if ($maxSize > 0 && $estimate['bytes'] > $maxSize) {
            $this->markFailed($archive, 'The gallery is larger than the configured archive limit.');

            return;
        }

        $tempDirectory = $this->temporaryDirectory();

        if ($tempDirectory === null) {
            $this->markFailed($archive, 'Could not create the temporary archive directory.');

            return;
        }

        $freeSpace = @disk_free_space($tempDirectory);
        $concurrency = $this->downloadConcurrency();
        $downloadBuffer = $this->downloadBufferBytes($gallery, $archive->variant, $concurrency);
        $requiredSpace = (int) ceil($estimate['bytes'] * 1.05) + $downloadBuffer;

        if ($freeSpace !== false && $requiredSpace > 0 && $requiredSpace > $freeSpace * 0.9) {
            Log::warning('Not enough free disk space to build the gallery archive.', [
                'archive_id' => $archive->id,
                'free_bytes' => $freeSpace,
                'zip_estimate_bytes' => $estimate['bytes'],
                'download_buffer_bytes' => $downloadBuffer,
                'required_bytes' => $requiredSpace,
                'concurrency' => $concurrency,
            ]);

            $this->markFailed($archive, 'Not enough free disk space to build the gallery ZIP.');

            return;
        }

        $tempPath = tempnam($tempDirectory, 'gallery-archive-');

        if ($tempPath === false) {
            $this->markFailed($archive, 'Could not create a temporary archive file.');

            return;
        }

        $uploadedPath = null;

        try {
            $counts = $this->createZipFile($archive, $tempPath);

            if ($counts === null) {
                return; // Invalidated while building.
            }

            $fresh = GalleryArchive::find($archive->id);

            if (! $fresh || $fresh->status !== 'building') {
                return;
            }

            $archive = $fresh;
            $path = $this->archivePath($archive);
            $uploadedPath = $path;

            Storage::disk($this->disk())->putFileAs(
                dirname($path),
                new File($tempPath),
                basename($path),
                ['mimetype' => 'application/zip'],
            );

            $fresh = GalleryArchive::find($archive->id);

            if (! $fresh || $fresh->status !== 'building') {
                Storage::disk($this->disk())->delete($path);

                return;
            }

            $updated = GalleryArchive::query()
                ->whereKey($fresh->id)
                ->where('status', 'building')
                ->update([
                    'status' => 'ready',
                    'disk' => $this->disk(),
                    'path' => $path,
                    'size_bytes' => filesize($tempPath) ?: null,
                    'photos_processed' => $counts['images'] + $counts['videos'] + $counts['pdfs'],
                    'photos_skipped' => $counts['skipped'],
                    'images_count' => $counts['images'],
                    'videos_count' => $counts['videos'],
                    'pdfs_count' => $counts['pdfs'],
                    'completed_at' => now(),
                    'expires_at' => now()->addDays($this->ttlDays()),
                    'failure_reason' => null,
                ]);

            if ($updated === 0) {
                Storage::disk($this->disk())->delete($path);
            }
        } catch (Throwable $exception) {
            if ($uploadedPath) {
                Storage::disk($this->disk())->delete($uploadedPath);
            }

            $fresh = GalleryArchive::find($archive->id);

            if ($fresh && $fresh->status === 'building') {
                GalleryArchive::query()
                    ->whereKey($fresh->id)
                    ->where('status', 'building')
                    ->update([
                        'status' => 'pending',
                        'failure_reason' => Str::limit($exception->getMessage(), 500),
                    ]);
            }

            throw $exception;
        } finally {
            @unlink($tempPath);
        }
    }

    protected function temporaryDirectory(): ?string
    {
        $directory = (string) config('picstome.gallery_archives.temp_path', '');

        if ($directory === '') {
            $directory = sys_get_temp_dir();
        }

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            Log::warning('Gallery archive temporary directory could not be created.', [
                'path' => $directory,
            ]);

            return null;
        }

        return $directory;
    }

    /**
     * @param  array<int, string>  $usedNames
     */
    protected function addPhotoToZip(ZipStream $zip, Photo $photo, array &$usedNames): string
    {
        if ($photo->isVideo()) {
            $type = 'video';
            $entryName = $photo->name;
        } elseif ($photo->isPdf()) {
            $type = 'pdf';
            $entryName = $photo->name;
        } elseif ($photo->isImage()) {
            $type = 'image';
            $entryName = $this->imageEntryName($photo);
        } else {
            // RAW-only rows are skipped: the processed JPEG is expected on path.
            return 'skipped';
        }

        $entryName = $this->uniqueEntryName($entryName, $usedNames);
        $stream = null;

        try {
            $stream = Storage::disk($photo->diskOrDefault())->readStream($photo->path);

            if (! is_resource($stream)) {
                return 'skipped';
            }

            $zip->addFileFromStream($entryName, $stream);
        } catch (Throwable $exception) {
            Log::warning('Gallery archive skipped an unreadable file.', [
                'photo_id' => $photo->id,
                'path' => $photo->path,
                'error' => $exception->getMessage(),
            ]);

            return 'skipped';
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $type;
    }

    /**
     * @return array{images: int, videos: int, pdfs: int, skipped: int}|null
     */
    protected function createZipFile(GalleryArchive $archive, string $tempPath): ?array
    {
        $stream = fopen($tempPath, 'wb');

        if ($stream === false) {
            throw new \RuntimeException('Could not open the temporary archive file.');
        }

        $zip = null;
        $finished = false;
        $usedNames = [];
        $counts = ['images' => 0, 'videos' => 0, 'pdfs' => 0, 'skipped' => 0];

        try {
            $zip = new ZipStream(
                outputStream: $stream,
                defaultCompressionMethod: CompressionMethod::STORE,
                sendHttpHeaders: false,
                outputName: $archive->filename,
            );

            $entries = $this->archiveEntries($archive);

            if ($this->canDownloadInParallel($entries)) {
                $counts['skipped'] = max(0, (int) $archive->photos_total - count($entries));
                $completed = $this->addEntriesInParallel(
                    $zip,
                    $entries,
                    $archive,
                    $usedNames,
                    $counts,
                    dirname($tempPath),
                );
            } else {
                $completed = $this->addEntriesSequentially($zip, $archive, $usedNames, $counts);
            }

            if (! $completed) {
                return null;
            }

            $zip->finish();
            $finished = true;

            $archive->updateQuietly([
                'photos_processed' => $counts['images'] + $counts['videos'] + $counts['pdfs'],
                'photos_skipped' => $counts['skipped'],
                'images_count' => $counts['images'],
                'videos_count' => $counts['videos'],
                'pdfs_count' => $counts['pdfs'],
            ]);

            return $counts;
        } finally {
            if ($zip && ! $finished) {
                // Do not write a central directory for a cancelled/partial archive.
                unset($zip);
            }

            fclose($stream);
        }
    }

    /**
     * @param  array<int, string>  $usedNames
     * @param  array{images: int, videos: int, pdfs: int, skipped: int}  $counts
     */
    protected function addEntriesSequentially(ZipStream $zip, GalleryArchive $archive, array &$usedNames, array &$counts): bool
    {
        $photos = $this->photosQuery($archive->gallery, $archive->variant)->orderBy('id')->cursor();
        $index = 0;
        $updatedAt = microtime(true);

        foreach ($photos as $photo) {
            $index++;

            if ($index % 25 === 1 && ! $this->isArchiveStillBuilding($archive)) {
                return false;
            }

            $result = $this->addPhotoToZip($zip, $photo, $usedNames);

            if ($result === 'skipped') {
                $counts['skipped']++;
            } else {
                $counts[$result === 'image' ? 'images' : ($result === 'video' ? 'videos' : 'pdfs')]++;
            }

            if ($index % 25 === 0 || microtime(true) - $updatedAt >= 2) {
                $archive->updateQuietly([
                    'photos_processed' => $counts['images'] + $counts['videos'] + $counts['pdfs'],
                    'photos_skipped' => $counts['skipped'],
                    'images_count' => $counts['images'],
                    'videos_count' => $counts['videos'],
                    'pdfs_count' => $counts['pdfs'],
                ]);
                $updatedAt = microtime(true);
            }
        }

        return true;
    }

    /**
     * Download S3 files concurrently while writing the ZIP in its original
     * order. At most `download_concurrency` files are kept ahead of the ZIP
     * writer, so disk usage stays bounded.
     *
     * @param  array<int, array{photo: Photo, type: 'image'|'video'|'pdf', name: string}>  $entries
     * @param  array<int, string>  $usedNames
     * @param  array{images: int, videos: int, pdfs: int, skipped: int}  $counts
     */
    protected function addEntriesInParallel(
        ZipStream $zip,
        array $entries,
        GalleryArchive $archive,
        array &$usedNames,
        array &$counts,
        string $tempDirectory,
    ): bool {
        $total = count($entries);

        if ($total === 0) {
            return true;
        }

        $concurrency = min($this->downloadConcurrency(), $total);
        $multi = curl_multi_init();
        $active = [];
        $completed = [];
        $failed = [];
        $nextToStart = 0;
        $nextToWrite = 0;
        $advanced = 0;
        $updatedAt = microtime(true);
        $startedAt = microtime(true);

        $write = function (int $index, string $path) use ($zip, $entries, &$usedNames, &$counts, $archive, &$updatedAt, &$advanced): bool {
            $entry = $entries[$index];
            $entryName = $this->uniqueEntryName($entry['name'], $usedNames);
            $stream = @fopen($path, 'rb');

            if ($stream === false) {
                $counts['skipped']++;
            } else {
                try {
                    $zip->addFileFromStream($entryName, $stream);
                    $counts[$entry['type'] === 'image' ? 'images' : ($entry['type'] === 'video' ? 'videos' : 'pdfs')]++;
                } catch (Throwable $exception) {
                    $counts['skipped']++;
                    Log::warning('Gallery archive skipped an unreadable downloaded file.', [
                        'photo_id' => $entry['photo']->id,
                        'path' => $entry['photo']->path,
                        'error' => $exception->getMessage(),
                    ]);
                } finally {
                    fclose($stream);
                }
            }

            @unlink($path);
            $advanced++;

            if ($advanced % 25 === 0 || microtime(true) - $updatedAt >= 2) {
                $archive->updateQuietly([
                    'photos_processed' => $counts['images'] + $counts['videos'] + $counts['pdfs'],
                    'photos_skipped' => $counts['skipped'],
                    'images_count' => $counts['images'],
                    'videos_count' => $counts['videos'],
                    'pdfs_count' => $counts['pdfs'],
                ]);
                $updatedAt = microtime(true);
            }

            return $advanced % 25 !== 0 || $this->isArchiveStillBuilding($archive);
        };

        $start = function (int $index) use ($entries, $multi, $tempDirectory, &$active, &$failed): void {
            $entry = $entries[$index];
            $photo = $entry['photo'];

            try {
                $url = Storage::disk($photo->diskOrDefault())->temporaryUrl(
                    $photo->path,
                    now()->addHours(6),
                );
            } catch (Throwable $exception) {
                $failed[$index] = true;
                Log::warning('Gallery archive could not create a download URL.', [
                    'photo_id' => $photo->id,
                    'path' => $photo->path,
                    'error' => $exception->getMessage(),
                ]);

                return;
            }

            $path = tempnam($tempDirectory, 'gallery-archive-download-');

            if ($path === false) {
                $failed[$index] = true;

                return;
            }

            $file = @fopen($path, 'wb');

            if ($file === false) {
                @unlink($path);
                $failed[$index] = true;

                return;
            }

            $handle = curl_init($url);

            if ($handle === false) {
                fclose($file);
                @unlink($path);
                $failed[$index] = true;

                return;
            }

            curl_setopt_array($handle, [
                CURLOPT_FILE => $file,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_TIMEOUT => max(60, (int) config('picstome.gallery_archives.timeout', 3600)),
            ]);

            if (curl_multi_add_handle($multi, $handle) !== CURLM_OK) {
                curl_close($handle);
                fclose($file);
                @unlink($path);
                $failed[$index] = true;

                return;
            }

            $active[spl_object_id($handle)] = [
                'handle' => $handle,
                'file' => $file,
                'path' => $path,
                'index' => $index,
            ];
        };

        $cleanup = function () use (&$active, &$completed, $multi): void {
            foreach ($active as $item) {
                curl_multi_remove_handle($multi, $item['handle']);
                curl_close($item['handle']);
                fclose($item['file']);
                @unlink($item['path']);
            }

            foreach ($completed as $path) {
                @unlink($path);
            }

            $active = [];
            $completed = [];
            curl_multi_close($multi);
        };

        try {
            while ($nextToWrite < $total) {
                while ($nextToStart < $total && $nextToStart < $nextToWrite + $concurrency) {
                    $start($nextToStart);
                    $nextToStart++;
                }

                while ($nextToWrite < $total && isset($failed[$nextToWrite])) {
                    unset($failed[$nextToWrite]);
                    $counts['skipped']++;
                    $advanced++;
                    $nextToWrite++;

                    if ($advanced % 25 === 0 && ! $this->isArchiveStillBuilding($archive)) {
                        return false;
                    }
                }

                if ($nextToWrite >= $total) {
                    break;
                }

                if ($active === []) {
                    break;
                }

                $status = curl_multi_exec($multi, $running);

                if ($status === CURLM_CALL_MULTI_PERFORM) {
                    continue;
                }

                if ($status !== CURLM_OK) {
                    throw new \RuntimeException('The S3 download pool failed.');
                }

                if ($running > 0 && curl_multi_select($multi, 1.0) === -1) {
                    usleep(1000);
                }

                while ($info = curl_multi_info_read($multi)) {
                    $handle = $info['handle'];
                    $id = spl_object_id($handle);
                    $item = $active[$id] ?? null;

                    if ($item === null) {
                        curl_multi_remove_handle($multi, $handle);
                        curl_close($handle);

                        continue;
                    }

                    $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
                    $error = curl_error($handle);

                    curl_multi_remove_handle($multi, $handle);
                    curl_close($handle);
                    fclose($item['file']);
                    unset($active[$id]);

                    if ($httpCode === 200) {
                        $completed[$item['index']] = $item['path'];
                    } else {
                        @unlink($item['path']);
                        $failed[$item['index']] = true;
                        Log::warning('Gallery archive could not download a file.', [
                            'photo_id' => $entries[$item['index']]['photo']->id,
                            'path' => $entries[$item['index']]['photo']->path,
                            'http_status' => $httpCode,
                            'error' => $error,
                        ]);
                    }
                }

                while (isset($completed[$nextToWrite])) {
                    $path = $completed[$nextToWrite];
                    unset($completed[$nextToWrite]);

                    if (! $write($nextToWrite, $path)) {
                        return false;
                    }

                    $nextToWrite++;
                }
            }

            Log::info('Gallery archive parallel download completed.', [
                'archive_id' => $archive->id,
                'concurrency' => $concurrency,
                'files' => $total,
                'skipped' => $counts['skipped'],
                'duration_seconds' => round(microtime(true) - $startedAt, 2),
            ]);

            return $nextToWrite >= $total;
        } finally {
            $cleanup();
        }
    }

    /**
     * @return array<int, array{photo: Photo, type: 'image'|'video'|'pdf', name: string}>
     */
    protected function archiveEntries(GalleryArchive $archive): array
    {
        $entries = [];

        foreach ($this->photosQuery($archive->gallery, $archive->variant)->orderBy('id')->cursor() as $photo) {
            if ($photo->isVideo()) {
                $type = 'video';
                $name = $photo->name;
            } elseif ($photo->isPdf()) {
                $type = 'pdf';
                $name = $photo->name;
            } elseif ($photo->isImage()) {
                $type = 'image';
                $name = $this->imageEntryName($photo);
            } else {
                continue;
            }

            $entries[] = ['photo' => $photo, 'type' => $type, 'name' => $name];
        }

        return $entries;
    }

    /**
     * @param  array<int, array{photo: Photo, type: 'image'|'video'|'pdf', name: string}>  $entries
     */
    protected function canDownloadInParallel(array $entries): bool
    {
        if (count($entries) < 2 || $this->downloadConcurrency() < 2 || ! function_exists('curl_multi_init')) {
            return false;
        }

        foreach ($entries as $entry) {
            if (! (Storage::disk($entry['photo']->diskOrDefault())->getAdapter() instanceof AwsS3V3Adapter)) {
                return false;
            }
        }

        return true;
    }

    protected function downloadConcurrency(): int
    {
        return max(1, min(20, (int) config('picstome.gallery_archives.download_concurrency', 5)));
    }

    protected function isArchiveStillBuilding(GalleryArchive $archive): bool
    {
        return GalleryArchive::whereKey($archive->id)->value('status') === 'building';
    }

    protected function imageEntryName(Photo $photo): string
    {
        $extension = strtolower(pathinfo($photo->path, PATHINFO_EXTENSION));

        if ($photo->raw_path && in_array($extension, ['jpg', 'jpeg'], true)) {
            return $photo->downloadFileName();
        }

        return $photo->name;
    }

    /**
     * @param  array<int, string>  $usedNames
     */
    protected function uniqueEntryName(string $name, array &$usedNames): string
    {
        $name = basename(str_replace('\\', '/', $name)) ?: 'file';
        $candidate = $name;
        $counter = 1;
        $pathInfo = pathinfo($name);
        $extension = isset($pathInfo['extension']) ? '.'.$pathInfo['extension'] : '';
        $base = $pathInfo['filename'] ?? 'file';

        while (in_array(mb_strtolower($candidate), $usedNames, true)) {
            $candidate = $base.' ('.$counter.')'.$extension;
            $counter++;
        }

        $usedNames[] = mb_strtolower($candidate);

        return $candidate;
    }
}
