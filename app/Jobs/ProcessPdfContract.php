<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Notifications\ContractPdfFailed;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessPdfContract implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Transient storage or renderer failures deserve a second chance; a
     * broken template will fail again and notify the owner.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 60];

    public int $timeout = 300;

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
            Pdf::setOption(['letter' => 'letter', 'isRemoteEnabled' => false])->loadView('pdf.contract', [
                'contract' => $this->contract,
            ])
        );

        app()->setLocale($previousLocale);

        NotifyContractExecuted::dispatch($this->contract);
    }

    /**
     * The contract stays executed (the signatures are legally binding); only
     * the PDF is missing, so tell the owner they can request it again instead
     * of leaving a silent 404 on the download button.
     */
    public function failed(?Throwable $exception): void
    {
        report($exception);

        $contract = Contract::query()->find($this->contract->getKey());

        $contract?->team->owner?->notify(new ContractPdfFailed($contract));
    }
}
