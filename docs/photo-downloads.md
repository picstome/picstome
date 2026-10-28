# Individual photo downloads

Individual downloads are served by the storage provider when the disk supports
temporary URLs. The application still authorizes every request; it only issues
the short-lived URL after the checks pass.

## Flow

1. The existing route receives the download request:
   - owner: `galleries.photos.download`
   - shared gallery: `shares.photos.download`
   - historical version: `galleries.photos.versions.download`
2. Laravel checks the team policy, the shared gallery flags and the gallery
   password when applicable.
3. `PhotoDownloadService` asks the photo disk for a presigned URL.
4. When the disk supports temporary URLs (S3), the response is a `302` redirect
   to that URL. Storage serves the file directly and supports HTTP range
   requests, so large files can be resumed.
5. When the disk does not support temporary URLs (for example the local
   `public` disk), the existing streamed `Storage::download()` response is
   returned as a fallback.

Processed JPGs, RAW files and historical versions use the same flow. PDF inline
responses are unchanged.

## URL lifetime

| File | Default TTL | Configuration |
|---|---|---|
| Photos and RAW files | 10 minutes | `PICSTOME_PHOTO_DOWNLOAD_URL_TTL_MINUTES` |
| Videos | 6 hours | `PICSTOME_VIDEO_DOWNLOAD_URL_TTL_MINUTES` |

Videos use a longer lifetime because they are large and may need to be resumed
after a pause. The expiry is checked when the storage request starts; an
already-running download is not interrupted when the URL expires. If a client
resumes after the TTL, it needs to click the download again to obtain a fresh
URL.

## Security

- The bucket remains private. No object ACLs or public URLs are created.
- URLs are generated only after the authorization checks and are never rendered
  in the page HTML.
- A leaked URL grants access until it expires, so the TTL is deliberately
  short for photos. Videos use a longer TTL for practical resume support.
- Revoking a URL before its expiry is not supported. Delete the object from
  storage if immediate revocation is required.
- Presigned URLs work with S3-compatible providers as long as they support
  SigV4 presigned `GetObject` requests. Verify the staging endpoint before
  deploying.

## Tests

Tests use `Storage::fake()` and `Storage::disk('s3')->buildTemporaryUrlsUsing()`
to assert the redirect and options without contacting real S3. A local disk
test covers the streamed fallback.

Related: #279 (this feature) and #280 (full-gallery ZIP archives).
