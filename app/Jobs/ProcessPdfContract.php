<?php

namespace App\Jobs;

use App\Models\Contract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Spatie\LaravelPdf\Facades\Pdf;

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
            Pdf::view('pdf.contract', ['contract' => $this->contract])
        );

        NotifyContractExecuted::dispatch($this->contract);
    }
}
