# Hybrid Storage Plan for Gallery Photos

## Overview

This document outlines the plan to refactor photo storage in the application to use a hybrid approach:

- **Initial Upload:** Photos are first uploaded to the local `public` disk as a temporary measure.
- **Processing:** The `ProcessPhoto` job processes the photo, generates thumbnails and resized images, and stores all processed files in S3.
- **Persistence:** The `Photo` model records which disk the final photo and its derivatives are stored on.
- **Retrieval:** All reads, URL generations, and deletions use the correct disk as stored in the database.

---

## Goals

- Support scalable, cloud-based storage for processed images.
- Maintain a simple, reliable upload experience.
- Ensure all file operations (read, URL, delete) use the correct storage disk.
- Maintain backward compatibility for existing photos.

---

## Implementation Steps

1. **Database Migration**
    - Add a nullable `disk` column to the `photos` table.
    - Default to `'public'` for existing records.

2. **Photo Model Update**
    - Add the `disk` attribute to the `Photo` model.
    - Ensure it is fillable and/or cast appropriately.

3. **Upload Logic**
    - All new photo uploads are saved to the `public` disk as a temporary file.

4. **ProcessPhoto Job Refactor**
    - Read the original photo from the `public` disk.
    - Process and save the final photo, thumbnail, and resized images to the S3 disk.
    - Update the `Photo` model’s `path`, `thumb_path`, and `disk` attributes to reflect S3.
    - Delete the temporary file from the `public` disk.

5. **File Retrieval and Deletion**
    - Refactor all code that reads, generates URLs, or deletes photos to use `$photo->disk` instead of a hardcoded disk name.

6. **Testing**
    - Update and expand tests to cover both local and S3 storage flows.
    - Use `Storage::fake('public')` and `Storage::fake('s3')` in tests.

7. **Documentation**
    - Update `.env.example` and `README.md` to explain S3 configuration and the new storage flow.

---

## Edge Cases & Considerations

- **Backwards Compatibility:**  
  Old photos without a `disk` value should default to `public`.
- **Atomicity:**  
  Only update the `Photo` model after all S3 uploads succeed.
- **Temporary File Cleanup:**  
  Always delete the original from `public` after successful S3 upload.
- **Permissions:**  
  Ensure S3 bucket permissions allow for the required access (public or signed URLs).

---

## Summary of the New Flow

1. **User uploads photo** → saved to `public` disk (temporary).
2. **ProcessPhoto job**:
    - Reads from `public`.
    - Processes and saves to `s3`.
    - Updates `Photo` model with new paths and disk.
    - Deletes original from `public`.
3. **All reads/URLs/deletes** use the disk stored in the `Photo` model.

---

## Example Code Snippets

**Saving a file (initial upload):**

```php
$path = Storage::disk('public')->putFile('photos', $uploadedFile);
$photo->path = $path;
$photo->disk = 'public';
$photo->save();
```

**Processing and moving to S3:**

```php
// In ProcessPhoto job
$finalPath = Storage::disk('s3')->putFile('photos', $processedFile);
$photo->path = $finalPath;
$photo->disk = 's3';
$photo->save();
Storage::disk('public')->delete($originalPath);
```

**Retrieving a URL:**

```php
$url = Storage::disk($photo->disk ?? 'public')->url($photo->path);
```

---

## Configuration

- Add S3 credentials and disk configuration to `.env.example`.
- Document how to switch between local and S3 storage.

---
