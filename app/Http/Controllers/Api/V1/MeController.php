<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AccountLimitsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * The token owner and the plan limits of their current team.
     *
     * `avatar_url` is only sent for an uploaded profile photo; the generated
     * initials avatar is left out so clients can render their own fallback.
     */
    public function __invoke(Request $request, AccountLimitsService $limits): JsonResponse
    {
        $user = $request->user();
        $team = $user->currentTeam;
        $account = $limits->forTeam($team);

        return response()->json([
            'email' => $user->email,
            'display_name' => $user->name,
            'avatar_url' => $user->avatar_path ? $user->avatar_url : null,
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
            ],
            'plan' => $account['plan'],
            'limits' => $account['limits'],
        ]);
    }
}
