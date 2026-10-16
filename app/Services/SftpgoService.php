<?php

namespace App\Services;

use App\Models\FtpAccount;
use Illuminate\Support\Facades\Http;

class SftpgoService
{
    /**
     * Whether SFTPGo provisioning is configured.
     */
    public function isEnabled(): bool
    {
        return config('picstome.ftp_uploads.sftpgo_url') !== null
            && config('picstome.ftp_uploads.sftpgo_api_key') !== null;
    }

    /**
     * Create the SFTPGo user for the given account.
     */
    public function createUser(FtpAccount $account): bool
    {
        return $this->request('post', '/api/v2/users', $this->userPayload($account)) !== null;
    }

    /**
     * Update the SFTPGo user (e.g. after toggling its status).
     */
    public function updateUser(FtpAccount $account): bool
    {
        return $this->request('put', '/api/v2/users/'.$account->username, $this->userPayload($account)) !== null;
    }

    /**
     * Remove the SFTPGo user for the given account.
     */
    public function deleteUser(FtpAccount $account): bool
    {
        return $this->request('delete', '/api/v2/users/'.$account->username) !== null;
    }

    /**
     * The payload for creating or updating the account's SFTPGo user.
     *
     * home_dir is intentionally omitted: SFTPGo then defaults it to its own
     * data directory per user (created on first login), which works for both
     * local and S3-backed filesystems.
     *
     * @return array<string, mixed>
     */
    protected function userPayload(FtpAccount $account): array
    {
        $payload = [
            'username' => $account->username,
            'password' => $account->password,
            'status' => $account->active ? 1 : 0,
            'permissions' => ['/' => ['*']],
        ];

        if ($this->s3BackendEnabled()) {
            $disk = config('filesystems.disks.s3');

            $payload['filesystem'] = [
                'provider' => 1,
                's3config' => [
                    'bucket' => $disk['bucket'],
                    'region' => $disk['region'],
                    'endpoint' => $disk['endpoint'] ?? '',
                    'access_key' => $disk['key'],
                    'access_secret' => ['status' => 'Plain', 'payload' => $disk['secret']],
                    'key_prefix' => $account->inboxPath().'/',
                    'force_path_style' => (bool) ($disk['use_path_style_endpoint'] ?? false),
                ],
            ];
        }

        return $payload;
    }

    protected function s3BackendEnabled(): bool
    {
        return (bool) config('picstome.ftp_uploads.sftpgo_s3_backend', true);
    }

    /**
     * Send a request to the SFTPGo admin API. Returns the response, or null
     * when SFTPGo is not configured (no-op).
     *
     * @param  array<string, mixed>|null  $data
     */
    protected function request(string $method, string $endpoint, ?array $data = null): ?\Illuminate\Http\Client\Response
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $response = Http::baseUrl(rtrim(config('picstome.ftp_uploads.sftpgo_url'), '/'))
            ->timeout(10)
            ->withHeaders(['X-SFTPGO-API-KEY' => config('picstome.ftp_uploads.sftpgo_api_key')])
            ->{$method}($endpoint, $data);

        if ($response->serverError() || $response->clientError()) {
            throw new \RuntimeException("SFTPGo API request to {$endpoint} failed: {$response->status()}");
        }

        return $response;
    }
}
