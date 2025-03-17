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
        $this->contract->signatures->each(function (Signature $signature) {
            Notification::route('mail', $signature->email)->notify(new ContractExecuted($this->contract));
        });
    }
}
