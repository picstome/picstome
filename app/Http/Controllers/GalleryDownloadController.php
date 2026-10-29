<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use App\Models\GalleryArchive;
use App\Services\GalleryArchiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Owner-facing full-gallery download.
 *
 * When archives are enabled the request returns a presigned S3 URL once the
 * ZIP is ready, or redirects to the gallery Download tab while it is built.
 */
class GalleryDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        Gallery $gallery,
        GalleryArchiveService $service,
    ): RedirectResponse|StreamedResponse {
        if (! $service->enabled()) {
            return $gallery->download($request->boolean('favorites'));
        }

        $archive = $service->findOrCreate(
            $gallery,
            $request->boolean('favorites') ? 'favorites' : 'all',
            Auth::user(),
        );

        if ($archive->isReady()) {
            $archive->recordDownload();

            return redirect()->away($service->temporaryUrl($archive));
        }

        return redirect()->route('galleries.show', [
            'gallery' => $gallery,
            'activeTab' => 'download',
        ]);
    }

    public function archive(
        Gallery $gallery,
        GalleryArchive $archive,
        GalleryArchiveService $service,
    ): RedirectResponse {
        abort_unless($archive->gallery_id === $gallery->id, 404);

        if (! $archive->isReady()) {
            return redirect()->route('galleries.show', [
                'gallery' => $gallery,
                'activeTab' => 'download',
            ]);
        }

        $archive->recordDownload();

        return redirect()->away($service->temporaryUrl($archive));
    }
}
