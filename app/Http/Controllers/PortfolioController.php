<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use App\Models\Team;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
    public function show(Request $request, string $handle, string $galleryUlid)
    {
        $team = Team::where('handle', strtolower($handle))->first();

        abort_if(!$team, 404);

        $gallery = Gallery::where('ulid', $galleryUlid)->first();

        abort_if(!$gallery, 404);

        // Ensure the gallery belongs to the team and is public
        if ($gallery->team_id !== $team->id || !$gallery->is_public) {
            abort(404);
        }

        $allPhotos = $gallery->photos()->with('gallery')->get();

        return view('portfolio.show', compact('gallery', 'team', 'allPhotos'));
    }
}
