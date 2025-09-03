<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;

class HandleController extends Controller
{
    public function show(Request $request, string $handle)
    {
        // Find team by handle (case-insensitive)
        $team = Team::where('handle', strtolower($handle))->first();

        if (!$team) {
            abort(404);
        }

        // If authenticated user owns this team, redirect to dashboard
        if ($request->user() && $request->user()->currentTeam?->id === $team->id) {
            return redirect('/dashboard');
        }

        // Otherwise, show team information
        return view('team.show', ['team' => $team]);
    }
}
