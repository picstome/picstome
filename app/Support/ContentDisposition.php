<?php

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Builds an attachment Content-Disposition header with an ASCII fallback.
 */
class ContentDisposition
{
    public static function attachment(string $filename, string $fallback = 'download'): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', '', Str::ascii($filename)) ?: $fallback;

        return HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename,
            $ascii,
        );
    }
}
