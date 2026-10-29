# Gallery ZIP archives

Full-gallery downloads are generated on demand and served as a single ZIP from
object storage. The application never builds a gallery ZIP while a gallery is
being viewed, shared or edited.

## Content rules

The ZIP contains one entry per current gallery photo:

- processed images (JPG, PNG or TIFF) stored on `photos.path`;
- videos stored on `photos.path`;
- PDFs stored on `photos.path`.

It never contains RAW files. When a RAW was processed, the extracted JPEG is
already stored on `photos.path` and is the entry that goes into the ZIP. If a
photo is still RAW-only because processing was skipped or failed, the archive
omits it and counts it as skipped; it does not run ExifTool again.

Historical `photo_versions` are not included. Only the current version of each
photo is used.

The ZIP is built with `STORE` (no recompression) and Zip64, and the entry names
are deduplicated.

## Lifecycle

1. A download is requested from the owner or shared gallery Download tab. The
   Prepare download button opens a confirmation modal; nothing is generated
   until the visitor confirms.
2. `GalleryArchiveService` creates or reuses a `gallery_archives` row and
   dispatches `BuildGalleryArchive`.
3. The job builds the ZIP in a temporary file on the queue worker and uploads
   it under `teams/{team_id}/galleries/{gallery_ulid}/archives/`.
4. Ready archives are served through a short-lived S3 presigned URL. S3 handles
   range/resume requests and PHP is not in the data path.
5. Archives expire after `PICSTOME_GALLERY_ARCHIVES_TTL_DAYS` (7 by default).
6. `gallery-archives:prune --force`, scheduled daily, deletes expired objects
   and old archive rows.

The archive row keeps the filename, size, content counts, generated date,
expiry, download count and failure reason. The owner and shared Download tabs
show this information.

## Invalidation

A ZIP is immutable, so changing a photo invalidates the whole archive:

- photo created, updated (name/path/raw path/disk/size/status/versions) or
  deleted;
- gallery renamed or its `keep_original_size` setting changed.

The archive is marked `expired` immediately and an object-deletion job is
queued. The archive stops being served at once; the queue worker removes the
S3 object shortly afterwards, outside the web request. The next download
request generates a new archive. Rows are kept as history until pruned.
Favorite changes only invalidate the `favorites` variant; they do not invalidate
the full-gallery archive.

If a photo changes while a ZIP is being built, the build checks the archive
status every 25 photos and again before/after upload. A cancelled build does
not upload a stale ZIP.

## Storage accounting

Temporary ZIPs are part of the gallery's temporary storage information. The
Download tab shows the current archive size and the total temporary ZIP storage
for that gallery (`StorageUsageService::archivesForGallery`). Archives are
**not** included in `Team::canStoreFile()` or storage-almost-full emails, so a
temporary ZIP never blocks uploads.

## Queue and worker requirements

The archive job is long-running compared with photo processing:

- `PICSTOME_GALLERY_ARCHIVES_TIMEOUT` defaults to 3600 seconds.
- The queue `retry_after` must be greater than that timeout. The example
  `.env.example` uses `DB_QUEUE_RETRY_AFTER=3900`. Restart queue workers after
  changing it.
- The worker needs enough temporary disk for the largest archive plus the
  largest processed file. The job checks a configured maximum size
  (`PICSTOME_GALLERY_ARCHIVES_MAX_SIZE_BYTES`, `0` means no limit) but does not
  stream directly to S3 yet.
- The job uses `WithoutOverlapping` with a lock lifetime based on the timeout.
- ExifTool is not needed by the archive job. It remains required by the normal
  photo-processing job for RAW files.

The ZIP is generated in a temporary local file and uploaded with the S3
multipart upload performed by the storage adapter. If galleries frequently
exceed the worker disk, replace this with direct multipart streaming in a later
iteration without changing the public download flow.

## Temporary files and cleanup

ZIPs are assembled in `PICSTOME_GALLERY_ARCHIVES_TEMP_PATH` (default
`storage/app/gallery-archives-temp`) instead of the system temp directory. The
file is removed in a `finally` block after the upload, but a killed worker can
still leave a large file behind.

The scheduled `temp:cleanup --force` command removes stale files:

- `php*` in the PHP upload temp directory (`/tmp` by default), older than 24h,
  only names without an extension; this skips logs, scripts and configuration files;
- `gallery-archive-*` in the configured temp directory and in the legacy
  system temp directory, older than the archive timeout plus a buffer (6h);
- files in `storage/app/photo-processing-temp`, older than 6h.

Run `php artisan temp:cleanup --dry-run` before a manual cleanup. The scheduled
run uses `--force`. The PHP upload files are created by PHP-FPM and may be owned
by `www-data`; if the deploy user cannot delete them, run the cleanup as the web
server user or add a root cron rule:

```bash
find /tmp -maxdepth 1 -type f -name 'php*' ! -name '*.*' -mmin +1440 -delete
```

```cron
# /etc/cron.d/picstome-temp-cleanup
15 4 * * * root find /tmp -maxdepth 1 -type f -name 'php*' ! -name '*.*' -mmin +1440 -delete
```

Large uploads that exceed the FPM `request_terminate_timeout` leave these PHP
temp files behind. Raise that timeout (and the web server/proxy timeouts) above
the largest expected upload; `set_time_limit(300)` in the Livewire component
does not override FPM's request timeout.

## Feature flag and rollback

`PICSTOME_GALLERY_ARCHIVES_ENABLED=false` makes the download controllers use the
legacy `Gallery::download()` streamed ZIP. The Download tab shows a direct
download button instead of preparing an archive. The `gallery_archives` table is
additive, so it can remain in place during a rollback.

## Operations

- The bucket stays private. Presigned URLs are generated only after the owner or
  shared gallery checks pass and are valid for
  `PICSTOME_GALLERY_ARCHIVES_URL_TTL_MINUTES` (60 minutes by default).
- No S3 CORS configuration is required for this flow because the browser
  follows a redirect to storage instead of fetching the ZIP with JavaScript.
- Run `gallery-archives:prune --dry-run` before a manual cleanup. The scheduled
  run uses `--force`.
- Monitor failed archive jobs and the number of skipped files. Skipped files
  usually mean an object is missing or a RAW-only photo was never processed.

## Tests

The feature tests fake S3 and the queue. They cover:

- processed images, videos and PDFs;
- no RAW entries and current-version-only behavior;
- RAW-only and missing files counted as skipped;
- duplicate entry names;
- fingerprint reuse and invalidation on photo/gallery changes;
- owner/shared routes, password protection and presigned URLs;
- owner/shared Download tab information and owner actions;
- the legacy feature flag;
- the prune command and storage accounting.

Run `vendor/bin/pint --dirty` and `composer test` before publishing.
