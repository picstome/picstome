<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Models\Signature;
use App\Notifications\ContractExecuted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class NotifyContractExecuted implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Contract $contract)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $locale = $this->contract->team->owner->language ?? config('app.locale');

        $this->contract->signatures->each(function (Signature $signature) use ($locale) {
            Notification::route('mail', $signature->email)->notify(
                (new ContractExecuted($this->contract))->locale($locale)
            );
        });
    }
}
