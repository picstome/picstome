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
        // Queue workers run outside the SetLocale middleware, so the owner's
        // language must be applied manually before rendering the PDF.
        $previousLocale = app()->getLocale();

        app()->setLocale($this->contract->team->owner->language ?? config('app.locale'));

        $this->contract->updatePdfFile(
            Pdf::setOption(['letter' => 'letter', 'isRemoteEnabled' => true])->loadView('pdf.contract', [
                'contract' => $this->contract,
            ])
        );

        app()->setLocale($previousLocale);

        NotifyContractExecuted::dispatch($this->contract);
    }
}
