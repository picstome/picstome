<?php

namespace App\Jobs;

use App\Models\Contract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPdfContract implements ShouldQueue
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
        $this->contract->updatePdfFile(
            Pdf::setOption(['letter' => 'letter', 'isRemoteEnabled' => true])->loadView('pdf.contract', [
                'contract' => $this->contract,
            ])
        );

        NotifyContractExecuted::dispatch($this->contract);
    }
}
