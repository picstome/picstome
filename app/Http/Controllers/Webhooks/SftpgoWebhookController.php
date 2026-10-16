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

        if ($action !== 'upload' || $username === '' || $path === '') {
            Log::info('SFTPGo webhook ignored.', ['action' => $action, 'username' => $username, 'path' => $path]);

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
