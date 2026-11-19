<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * Removes a deleted account from every Acumbamail list.
 *
 * Mirrors `AddToAcumbamailList`: the account is added to the list matching the
 * UI language at registration and verification time, so both lists are
 * cleaned up. A missing token or list is a no-op, and a failed request is
 * ignored because the local account is already gone.
 */
class RemoveFromAcumbamailList implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60];

    public function __construct(public string $email) {}

    public function handle(): void
    {
        if (! config('services.acumbamail.auth_token')) {
            return;
        }

        $listIds = array_filter([
            config('services.acumbamail.list_id'),
            config('services.acumbamail.list_id_es'),
        ]);

        foreach (array_unique($listIds) as $listId) {
            Http::timeout(10)->post('https://acumbamail.com/api/1/deleteSubscriber/', [
                'auth_token' => config('services.acumbamail.auth_token'),
                'list_id' => $listId,
                'email' => $this->email,
            ]);
        }
    }
}
