<?php

namespace App\Http\Controllers\Webhooks;

use App\Jobs\IngestFtpUpload;
use App\Models\FtpAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SftpgoWebhookController
{
    /**
     * Receive SFTPGo "upload completed" events and ingest the file
     * immediately, instead of waiting for the next scheduler run. The
     * every-minute ingest command stays as a fallback.
     */
    public function __invoke(Request $request)
    {
        $token = (string) config('picstome.ftp_uploads.webhook_token');

        abort_if($token === '' || ! hash_equals($token, (string) $request->header('X-PICSTOME-TOKEN')), 401);

        $action = $request->input('action');
        $username = (string) $request->input('username', '');
        $path = (string) ($request->input('virtual_path') ?? $request->input('objectname', ''));
        $status = $request->input('status');
        $fileSize = $request->input('file_size');

        // Only ingest successful, non-empty uploads. Both fields are optional
        // so older webhook templates without them keep working.
        $failed = $status !== null && (int) $status !== 1;
        $empty = $fileSize !== null && (int) $fileSize <= 0;

        if ($action !== 'upload' || $username === '' || $path === '' || $failed || $empty) {
            Log::info('SFTPGo webhook ignored.', [
                'action' => $action,
                'username' => $username,
                'path' => $path,
                'status' => $status,
                'file_size' => $fileSize,
            ]);

            return response()->noContent();
        }

        $account = FtpAccount::where('username', $username)->first();

        if (! $account?->active) {
            return response()->noContent();
        }

        IngestFtpUpload::dispatch($account->id, $account->inboxPath().'/'.basename($path));

        return response()->noContent();
    }
}
