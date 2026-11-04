<?php

namespace App\Traits\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * Stable API error envelope used by the external v1 API.
 *
 * Clients branch on `code` (not on the human-readable message) so the message
 * can be translated without breaking integrations.
 */
trait RespondsWithApiErrors
{
    protected function apiError(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors' => (object) [],
            'code' => $code,
        ], $status);
    }
}
