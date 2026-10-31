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
- Regular images are published by overwriting the same S3 object. The public
  path is stable, so an already-rendered gallery never requests a file that was
  deleted while its processed replacement was being uploaded.
- `replaceCurrentVersion` reuses the current path when the extension does not
  change. Cross-format replacements keep the existing upload flow.
- A small WebP grid thumbnail is generated at `<path>.thumb.webp` and stored in
  `thumb_path`.
- The photo stays in `processing` while the job runs and in `warming` while
  `App\Jobs\WarmPhotoCdn` fetches the grid variants through the CDN. Only
  `processed` and `skipped` photos are exposed on guests, shared galleries and
  portfolio pages. Warming can be disabled with
  `PICSTOME_CDN_WARMING_ENABLED=false`.
- The upload dialog polls `uploadStatuses` for the ids returned by `save` and
  shows each file moving through queued, processing, warming and ready. Closing
  the dialog cancels the polling timer; the grid thumbnail still reflects the
  same status if the dialog was closed early.

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

## Annotations

Photo comments can be pinned to a normalized position on the image
(`photo_comments.x` / `y`, percentages of the rendered image). A pinned
comment stores the version number it was created on
(`annotated_version_number`) instead of a version row reference, so deleting
versions or running `photos:collapse-versions` does not break the data:

- Annotations are only created against the current version. The comparison
  modal is read-only for annotations.
- When the current version changes (new upload or restore), annotations from
  another version are flagged in the comment list and drawn dimmed on the photo.
- `replaceCurrentVersion` keeps the version number, so an in-place replacement
  does not mark annotations as outdated.

## Feature flag and rollback

`PICSTOME_FILE_VERSIONS_ENABLED=false` is the emergency deprecation mode. It:

- hides the versions UI for owners and shared galleries;
- ignores `is_share_versions_visible`;
- blocks new versions from web and FTP uploads;
- keeps existing history and files intact.

To remove history explicitly, use the dry-run-first command:

```bash
php artisan photos:collapse-versions
php artisan photos:collapse-versions --force
php artisan photos:collapse-versions --force --delete-files
```

The command keeps the current photo/version fields and deletes historical rows.
Historical files are only removed when `--delete-files` is explicit; files still
referenced by the current photo are protected.

`users.gallery_preferences` stores remembered gallery creation/share defaults.
Passwords and description text are never stored there.

## Storage accounting

- `photos.stored_size` and `photo_versions.stored_size` hold the bytes actually
  stored for each record, including the generated thumbnail.
- Current files + historical versions are summed by
  `App\Services\StorageUsageService`.
- `current_version_id` rows are excluded from version totals to avoid counting
  the current file twice.
- Deleting a photo or version also deletes its `thumb_path` through
  `DeleteFromDisk`.

Backfill thumbnails for existing media after deploying:

```bash
php artisan photos:generate-thumbnails --dry-run
php artisan photos:generate-thumbnails
php artisan storage:recalculate --dry-run
php artisan storage:recalculate
```

The thumbnail command is idempotent; pass `--force` to regenerate existing
thumbnails.

After deploying this feature, reconcile pre-existing sizes once:

```bash
php artisan migrate
php artisan storage:recalculate --dry-run
php artisan storage:recalculate
```

Restart queue workers after deployment so they load the new `ProcessPhoto`
logic.

## Deployment and rollback

The schema is created by one consolidated migration. It is guarded so it can
also be applied where the earlier split migrations were already run.

After deploy, run:

```bash
php artisan migrate
php artisan storage:recalculate --dry-run
php artisan storage:recalculate
```

Restart queue workers after deploying so they load the new `ProcessPhoto`
logic. The version files created after deploy are the one-way part of rollback:
keep them or explicitly collapse/delete history. See
`docs/development-rules.md` for the full deploy and rollback checklist.

## Tests

Relevant suites:

```bash
php artisan test --compact tests/Feature/PhotoIdentityTest.php
php artisan test --compact tests/Feature/PhotoUrlServiceTest.php
php artisan test --compact tests/Feature/PhotoVersionDownloadTest.php
php artisan test --compact tests/Feature/StorageUsageTest.php
php artisan test --compact tests/Feature/ProcessPhotoTest.php
php artisan test --compact tests/Feature/DeleteFromDiskTest.php
php artisan test --compact tests/Feature/RecalculateStoredSizesCommandTest.php
php artisan test --compact tests/Feature/CollapsePhotoVersionsCommandTest.php
php artisan test --compact tests/Feature/FileVersionsFeatureFlagTest.php
php artisan test --compact resources/views/pages/shares/photos/
```
