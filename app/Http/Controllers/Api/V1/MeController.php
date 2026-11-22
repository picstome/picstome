<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * The token owner, as shown by desktop clients (Picstome Sync).
     *
     * `avatar_url` is only sent for an uploaded profile photo; the generated
     * initials avatar is left out so clients can render their own fallback.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'email' => $user->email,
            'display_name' => $user->name,
            'avatar_url' => $user->avatar_path ? $user->avatar_url : null,
        ]);
    }
}
