<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Photoshoot;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DedupeCustomersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customers:dedupe {--execute : Apply the merges. Without it, dry-run.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merge duplicate customers sharing an email within a team (dry-run by default).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $plans = $this->duplicateGroups()
            ->map(fn (Collection $group) => $this->plan($group))
            ->values();

        if ($plans->isEmpty()) {
            $this->info('No duplicate customers found.');

            return self::SUCCESS;
        }

        foreach ($plans as $plan) {
            $this->line($this->describe($plan));
        }

        $merged = $plans->sum(fn (DedupPlan $plan) => $plan->duplicates->count());

        if (! $this->option('execute')) {
            $this->info("{$merged} duplicate customer(s) across {$plans->count()} group(s) would be merged.");
            $this->info('Dry run only. Pass --execute to apply the merges.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($plans) {
            foreach ($plans as $plan) {
                $this->apply($plan);
            }
        });

        $this->info("Merged {$merged} duplicate customer(s) across {$plans->count()} group(s).");

        if ($this->duplicateGroups()->isNotEmpty()) {
            $this->error('Some same-email duplicates remain after merging.');

            return self::FAILURE;
        }

        $this->info('Verified: no duplicate customers remain.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Collection<int, Customer>>
     */
    private function duplicateGroups(): Collection
    {
        return Customer::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->withCount('photoshoots')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Customer $customer) => $customer->team_id.'|'.Str::lower($customer->email))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->values();
    }

    /**
     * @param  Collection<int, Customer>  $group
     */
    private function plan(Collection $group): DedupPlan
    {
        $canonical = $group->sort(function (Customer $a, Customer $b) {
            if ($a->photoshoots_count !== $b->photoshoots_count) {
                return $b->photoshoots_count <=> $a->photoshoots_count;
            }

            return $a->id <=> $b->id;
        })->first();

        $duplicates = $group->reject(fn (Customer $customer) => $customer->is($canonical))->values();

        $name = $group
            ->filter(fn (Customer $customer) => filled($customer->name))
            ->sortByDesc(fn (Customer $customer) => mb_strlen($customer->name))
            ->first()
            ?->name ?? $canonical->name;

        $backfill = [];
        foreach (['birthdate', 'phone', 'notes'] as $field) {
            if (filled($canonical->{$field})) {
                continue;
            }

            $value = $duplicates->first(fn (Customer $customer) => filled($customer->{$field}))?->{$field};

            if ($value !== null) {
                $backfill[$field] = $value;
            }
        }

        return new DedupPlan($canonical, $duplicates, $name, $backfill);
    }

    private function apply(DedupPlan $plan): void
    {
        Photoshoot::query()
            ->whereIn('customer_id', $plan->duplicates->pluck('id'))
            ->update(['customer_id' => $plan->canonical->id]);

        $plan->canonical->fill(['name' => $plan->name, ...$plan->backfill])->save();

        Customer::query()
            ->whereIn('id', $plan->duplicates->pluck('id'))
            ->delete();
    }

    private function describe(DedupPlan $plan): string
    {
        $duplicates = $plan->duplicates
            ->map(fn (Customer $customer) => "#{$customer->id} ({$customer->name})")
            ->implode(', ');

        $backfill = $plan->backfill === [] ? 'none' : implode(', ', array_keys($plan->backfill));

        return sprintf(
            'Team %d: keep #%d (%s), merge %s, %d photoshoot(s) re-pointed, backfill: %s',
            $plan->canonical->team_id,
            $plan->canonical->id,
            $plan->canonical->name,
            $duplicates,
            $plan->duplicates->sum('photoshoots_count'),
            $backfill,
        );
    }
}

readonly class DedupPlan
{
    /**
     * @param  Collection<int, Customer>  $duplicates
     * @param  array<string, mixed>  $backfill
     */
    public function __construct(
        public Customer $canonical,
        public Collection $duplicates,
        public string $name,
        public array $backfill,
    ) {}
}
