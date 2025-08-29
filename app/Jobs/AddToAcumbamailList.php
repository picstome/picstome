<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class AddToAcumbamailList implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public string $name
    ) {}

    public function handle(): void
    {
        if (!config('services.acumbamail.auth_token') || !config('services.acumbamail.list_id')) {
            return;
        }

        Http::post('https://acumbamail.com/api/1/addSubscriber', [
            'auth_token' => config('services.acumbamail.auth_token'),
            'list_id' => config('services.acumbamail.list_id'),
            'merge_fields' => [
                'EMAIL' => $this->email,
                'NAME' => $this->name,
            ],
            'double_optin' => 0,
            'update_subscriber' => 0,
            'complete_json' => 0,
        ]);
    }
}
