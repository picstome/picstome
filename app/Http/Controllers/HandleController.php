<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;

class HandleController extends Controller
{
    public function show(Request $request, string $handle)
    {
        $team = Team::where('handle', strtolower($handle))->first();

        abort_if(!$team, 404);

        return view('handle.show', ['team' => $team]);

}

}
