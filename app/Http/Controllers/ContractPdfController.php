<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads the executed PDF of a contract owned by the photographer's team.
 */
class ContractPdfController extends Controller
{
    public function __invoke(Contract $contract): StreamedResponse
    {
        Gate::authorize('view', $contract);

        abort_unless($contract->isExecuted(), 404);

        abort_if(blank($contract->pdf_file_path), 404);

        $disk = Storage::disk(config('picstome.disk'));

        abort_unless($disk->exists($contract->pdf_file_path), 404);

        return $contract->download();
    }
}
