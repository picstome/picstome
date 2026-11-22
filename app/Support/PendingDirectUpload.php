<?php

namespace App\Support;

use Symfony\Component\Mime\MimeTypes;

/**
 * A file the browser is about to PUT straight to S3.
 *
 * Livewire's `GenerateSignedUploadUrl::forS3()` only reads the client name,
 * extension and MIME type of the file it signs. This stands in for the file
 * before any bytes exist on the server.
 */
class PendingDirectUpload
{
    public function __construct(public readonly string $name) {}

    public function getClientOriginalName(): string
    {
        return $this->name;
    }

    public function getClientOriginalExtension(): string
    {
        return pathinfo($this->name, PATHINFO_EXTENSION);
    }

    /**
     * Stored as the S3 object's Content-Type, which the CDN serves later.
     * Prefer the media type ("video/mp4", not "application/mp4").
     */
    public function getMimeType(): string
    {
        $types = MimeTypes::getDefault()->getMimeTypes(strtolower($this->getClientOriginalExtension()));

        foreach ($types as $type) {
            if (str_starts_with($type, 'image/') || str_starts_with($type, 'video/')) {
                return $type;
            }
        }

        return $types[0] ?? 'application/octet-stream';
    }
}
