<?php

namespace App\Traits;

/**
 * Shared download filenames for current photos and historical versions.
 *
 * When a processed file has a retained RAW, the processed copy is downloaded
 * as a JPG with the original base name. Otherwise the stored name is used.
 */
trait HasDownloadFilename
{
    public function downloadFileName(): string
    {
        if ($this->raw_path) {
            return pathinfo($this->name, PATHINFO_FILENAME).'.jpg';
        }

        return $this->name;
    }

    public function rawDownloadFileName(): string
    {
        return $this->name;
    }
}
