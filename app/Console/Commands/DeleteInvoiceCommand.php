<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceSetting;
use App\Models\Payment;
use App\Services\StorageUsageService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Removes an invoice together with its whole rectification family.
 *
 * Issued invoices must be kept for years, so this is an emergency tool for
 * data that never reached the client (for example, a first invoice issued by
 * mistake with a wrong amount and an equally wrong corrective invoice). It
 * deletes the invoices, their audit trail and their PDFs, and rewinds the
 * numbering and fingerprint state of the series so the next invoice can be
 * issued with the same number.
 *
 * The command refuses to break the fingerprint chain: a deleted invoice can
 * only be the last one of its series and exercise. In every other case the
 * right move is to issue a corrective invoice, not to delete.
 */
class DeleteInvoiceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:delete
        {reference : Full number or ULID of the invoice, original or corrective}
        {--team= : Team id, required when the reference is not unique}
        {--dry-run : Show what would be deleted without removing anything}
        {--force : Apply the deletion and unlink any payment from the invoices}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete an invoice with its rectifying invoices, audit trail and PDFs (dry-run by default).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (! $dryRun && ! $force) {
            $this->warn('Use --dry-run to inspect the scope or --force to delete the invoices.');

            return self::FAILURE;
        }

        $root = $this->resolveInvoice();

        if (! $root) {
            return self::FAILURE;
        }

        $invoices = $this->rectificationFamily($root);

        $this->table(
            ['ID', 'Number', 'Status', 'Total', 'Rectifies', 'PDF'],
            $invoices->map(fn (Invoice $invoice) => [
                $invoice->id,
                $invoice->full_number ?? '(draft)',
                $invoice->status,
                (float) $invoice->total,
                $invoice->rectifiesInvoice?->full_number ?? '—',
                $invoice->pdf_path ?? '—',
            ])->all(),
        );

        $payments = Payment::query()
            ->whereIn('invoice_id', $invoices->pluck('id'))
            ->count();

        if ($payments > 0) {
            $this->warn("{$payments} payment(s) point at these invoices and will be left without an invoice.");

            if (! $force) {
                $this->error('Pass --force to delete the invoices and unlink the payments.');

                return self::FAILURE;
            }
        }

        $broken = $this->brokenChainAfterDeletion($invoices);

        if ($broken !== null) {
            $this->error("Deleting these invoices would break the fingerprint chain of {$broken}.");
            $this->error('Only the last invoice of a series and exercise can be deleted. Issue a corrective invoice instead.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info(sprintf(
                'Dry run: %d invoice(s), %d event(s) and %d PDF(s) would be deleted.',
                $invoices->count(),
                InvoiceEvent::query()->whereIn('invoice_id', $invoices->pluck('id'))->count(),
                $invoices->filter(fn (Invoice $invoice) => filled($invoice->pdf_path))->count(),
            ));

            return self::SUCCESS;
        }

        $teamIds = $invoices->pluck('team_id')->unique()->values();
        $settings = $this->lockSettings($teamIds);

        DB::transaction(function () use ($invoices, $settings): void {
            $disk = Storage::disk(config('picstome.disk'));

            foreach ($invoices as $invoice) {
                if (filled($invoice->pdf_path)) {
                    $disk->delete($invoice->pdf_path);
                }

                Cache::forget('invoice-chain:'.$invoice->id);
            }

            InvoiceEvent::query()->whereIn('invoice_id', $invoices->pluck('id'))->delete();
            Payment::query()->whereIn('invoice_id', $invoices->pluck('id'))->update(['invoice_id' => null]);

            Invoice::query()
                ->whereIn('id', $invoices->pluck('id'))
                ->update(['rectifies_invoice_id' => null]);

            Invoice::query()->whereIn('id', $invoices->pluck('id'))->delete();

            $this->rewindSequences($settings);
        });

        foreach ($teamIds as $teamId) {
            StorageUsageService::forgetForTeam($teamId);
        }

        $this->info(sprintf(
            'Deleted %d invoice(s), their audit trail and their PDFs.',
            $invoices->count(),
        ));

        return self::SUCCESS;
    }

    /**
     * Find the invoice the user is pointing at.
     *
     * The reference can be the full number shown to the client or the ULID,
     * which is what the app uses in its URLs.
     */
    private function resolveInvoice(): ?Invoice
    {
        $reference = trim((string) $this->argument('reference'));

        $query = Invoice::query()
            ->with(['rectifiesInvoice', 'team.owner'])
            ->where(fn ($query) => $query
                ->where('full_number', $reference)
                ->orWhere('ulid', $reference));

        if (filled($this->option('team'))) {
            $query->where('team_id', (int) $this->option('team'));
        }

        $matches = $query->get();

        if ($matches->isEmpty()) {
            $this->error("No invoice found for [{$reference}].");

            return null;
        }

        if ($matches->count() > 1) {
            $this->error("The reference [{$reference}] matches more than one team. Pass --team=<id>.");
            $this->line('');
            $this->table(
                ['Team id', 'Team', 'Owner', 'Status', 'Total', 'Issued'],
                $matches->map(fn (Invoice $invoice) => [
                    $invoice->team_id,
                    $invoice->team?->name ?? '—',
                    $invoice->team?->owner?->email ?? '—',
                    $invoice->status,
                    (float) $invoice->total,
                    $invoice->issued_at?->format('Y-m-d H:i') ?? '—',
                ])->all(),
            );

            return null;
        }

        return $matches->first();
    }

    /**
     * The invoice itself, the original it corrects and every corrective
     * invoice issued against the same original.
     *
     * @return Collection<int, Invoice>
     */
    private function rectificationFamily(Invoice $invoice): Collection
    {
        $root = $invoice;

        while ($root->rectifies_invoice_id !== null && $root->rectifiesInvoice !== null) {
            $root = $root->rectifiesInvoice;
        }

        $family = collect([$root]);
        $pending = [$root->id];

        while ($pending !== []) {
            $children = Invoice::query()
                ->with('rectifiesInvoice')
                ->whereIn('rectifies_invoice_id', $pending)
                ->get();

            $pending = $children->pluck('id')->all();
            $family = $family->merge($children);
        }

        return $family->unique('id')->sortBy('id')->values();
    }

    /**
     * Detect whether removing the invoices would leave a gap in the chain of
     * any series and exercise.
     *
     * The remaining invoices are rehashed in order: their stored previous
     * fingerprint must still match the previous remaining invoice.
     */
    private function brokenChainAfterDeletion(Collection $invoices): ?string
    {
        $deletedIds = $invoices->pluck('id')->all();

        $series = $invoices
            ->map(fn (Invoice $invoice) => [$invoice->team_id, $invoice->series, $invoice->year])
            ->unique(fn (array $key) => implode('|', $key));

        foreach ($series as [$teamId, $seriesName, $year]) {
            if (blank($seriesName) || blank($year)) {
                continue;
            }

            $remaining = Invoice::query()
                ->where('team_id', $teamId)
                ->where('series', $seriesName)
                ->where('year', $year)
                ->whereNotNull('number')
                ->whereNotIn('id', $deletedIds)
                ->orderBy('number')
                ->get(['id', 'number', 'previous_fingerprint', 'fingerprint']);

            $previous = null;

            foreach ($remaining as $current) {
                if ($current->previous_fingerprint !== $previous) {
                    return "{$seriesName}-{$year} at number {$current->number}";
                }

                $previous = $current->fingerprint;
            }
        }

        return null;
    }

    /**
     * Settings of the affected teams, locked so no invoice is issued while
     * the sequence is rewritten.
     *
     * @param  Collection<int, int>  $teamIds
     * @return Collection<int, InvoiceSetting>
     */
    private function lockSettings(Collection $teamIds): Collection
    {
        return InvoiceSetting::query()
            ->whereIn('team_id', $teamIds)
            ->lockForUpdate()
            ->get();
    }

    /**
     * Rebuild the numbering state from the invoices that are still stored.
     *
     * A series without invoices left loses its entry, so the next invoice
     * starts again at number one with an empty chain.
     *
     * @param  Collection<int, InvoiceSetting>  $settings
     */
    private function rewindSequences(Collection $settings): void
    {
        foreach ($settings as $setting) {
            $state = $setting->invoice_sequence_state ?? [];

            foreach (array_keys($state) as $series) {
                foreach (array_keys($state[$series]) as $year) {
                    $last = Invoice::query()
                        ->where('team_id', $setting->team_id)
                        ->where('series', $series)
                        ->where('year', $year)
                        ->whereNotNull('number')
                        ->orderByDesc('number')
                        ->first(['number', 'fingerprint']);

                    if (! $last) {
                        unset($state[$series][$year]);

                        continue;
                    }

                    $state[$series][$year] = [
                        'last_number' => (int) $last->number,
                        'last_fingerprint' => $last->fingerprint,
                    ];
                }

                if ($state[$series] === []) {
                    unset($state[$series]);
                }
            }

            $setting->invoice_sequence_state = $state;
            $setting->save();
        }
    }
}
