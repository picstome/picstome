<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use App\Models\GalleryArchive;
use App\Services\GalleryArchiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public shared-gallery download.
 *
 * The shared flags and password middleware are checked before an archive is
 * requested. Ready archives are served through a presigned S3 URL; otherwise
 * the visitor is sent back to the shared Download tab with progress.
 */
class SharedGalleryDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        Gallery $gallery,
        GalleryArchiveService $service,
    ): RedirectResponse|StreamedResponse {
        abort_unless($gallery->is_shared, 404);
        abort_unless($gallery->is_share_downloadable, 401);

        if (! $service->enabled()) {
            return $gallery->download($request->boolean('favorites'));
        }

        $archive = $service->findOrCreate(
            $gallery,
            $request->boolean('favorites') ? 'favorites' : 'all',
        );

        if ($archive->isReady()) {
            $archive->recordDownload();

            return redirect()->away($service->temporaryUrl($archive));
        }

        return redirect()->route('shares.show', [
            'gallery' => $gallery,
            'slug' => $gallery->slug,
            'activeTab' => 'download',
        ]);
    }

    public function archive(
        Gallery $gallery,
        GalleryArchive $archive,
        GalleryArchiveService $service,
    ): RedirectResponse {
        abort_unless($gallery->is_shared, 404);
        abort_unless($gallery->is_share_downloadable, 401);
        abort_unless($archive->gallery_id === $gallery->id, 404);

        if (! $archive->isReady()) {
            return redirect()->route('shares.show', [
                'gallery' => $gallery,
                'slug' => $gallery->slug,
                'activeTab' => 'download',
            ]);
        }

        $archive->recordDownload();

        return redirect()->away($service->temporaryUrl($archive));
    }
}
