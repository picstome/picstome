<?php

namespace App\Http\Controllers;

use App\Models\Signature;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a signature image to the team that owns the contract.
 *
 * The bytes used to live on a public storage URL, which made the signature of
 * every signed contract fetchable by anyone who learned the path. The route is
 * authorised against the contract, and only raster extensions are served, so
 * a stored SVG can never be sniffed as a document. The contract PDF keeps
 * embedding the data URI, so nothing that already shipped depends on this URL.
 */
class SignatureImageController extends Controller
{
    public function __invoke(Signature $signature): StreamedResponse
    {
        Gate::authorize('view', $signature->contract);

        $path = $signature->signature_image_path;

        abort_if(blank($path), 404);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // The upload validation only accepts raster images; refuse anything
        // else here too so the browser never executes an SVG script.
        abort_unless(in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'], true), 404);

        $disk = Storage::disk(config('picstome.disk'));

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, 'signature.'.$extension, [
            'Content-Type' => $disk->mimeType($path) ?: 'image/'.$extension,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
