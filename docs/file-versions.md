# File Versions

## Overview

A gallery photo is a logical asset. Uploading a file whose logical identity
already exists in the gallery creates a new `PhotoVersion` instead of replacing
the existing photo. The current version stays on the `photos` row; older
versions live in `photo_versions`.

## Identity

`App\Services\PhotoIdentityService` builds `photos.identity_key` from the file
name:

- case-insensitive
- extension ignored for images
- `jpg`, `jpeg`, RAW formats, PNG and TIFF share the `image:` family
- video and PDF use separate families

Examples:

- `IMG_0001.JPG`
- `IMG_0001.jpeg`
- `IMG_0001.CR3`

all produce `image:img_0001`.

Matching is indexed through `photos.identity_key`. Web uploads and FTP ingestion
use the same logic, protected by a per-gallery + identity cache lock.

## Upload behavior

- New identity: creates a logical photo.
- Existing identity: snapshots the current photo into `photo_versions`, then
  makes the new upload current.
- RAW duplicates are versioned automatically. Other duplicates can be versioned,
  replaced or skipped from the uploader.
- RAW + JPG with the same base name are uploaded together and become versions.
- `source_name` keeps the latest original file name. Historical versions keep
  their own original file name.

## Processing

`App\Jobs\ProcessPhoto` receives the photo and the version count that was
current when it was dispatched.

- If a newer version exists when the job starts, the job processes the matching
  historical version instead of modifying the current one.
- If a newer version is added while the job is running, the final result is
  written to the historical version row.
- Raw processing stores the extracted JPG plus the optional original RAW path.

## Restore and delete

- Restoring a version copies it to the current photo without duplicating it in
  the version list.
- If the current photo is not already represented by a version row, it is
  snapshotted before restoring.
- Deleting a historical version removes only its own files and row.
- The current version cannot be deleted from the version modal.

## Sharing

`galleries.is_share_versions_visible` controls whether visitors can see and
compare versions. It is disabled by default and can be enabled in the share
gallery modal. Shared visitors can compare versions but cannot restore, delete
or download historical versions. Watermarked galleries render the watermark in
the comparison modal.

## Storage accounting

- `photos.stored_size` and `photo_versions.stored_size` hold the bytes actually
  stored for each record.
- Current files + historical versions are summed by
  `App\Services\StorageUsageService`.
- `current_version_id` rows are excluded from version totals to avoid counting
  the current file twice.

After deploying this feature, reconcile pre-existing sizes once:

```bash
php artisan migrate
php artisan storage:recalculate --dry-run
php artisan storage:recalculate
```

Restart queue workers after deployment so they load the new `ProcessPhoto`
logic.

## Tests

Relevant suites:

```bash
php artisan test --compact tests/Feature/PhotoIdentityTest.php
php artisan test --compact tests/Feature/StorageUsageTest.php
php artisan test --compact tests/Feature/ProcessPhotoTest.php
php artisan test --compact tests/Feature/DeleteFromDiskTest.php
php artisan test --compact tests/Feature/RecalculateStoredSizesCommandTest.php
php artisan test --compact resources/views/pages/shares/photos/
```
