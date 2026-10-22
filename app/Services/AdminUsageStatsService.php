<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Gallery;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Photo;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Builds the admin usage dashboard from local data only.
 *
 * "Paid" means an active Cashier subscription. Lifetime access is a separate
 * bucket because lifetime_at can be set by an admin grant as well as by a
 * lifetime purchase; the current schema cannot distinguish the two.
 */
class AdminUsageStatsService
{
    private const CACHE_PREFIX = 'admin-usage-stats:v2';

    private const METRICS = ['photos', 'galleries', 'contracts', 'users', 'paid_users', 'lifetime_users', 'invoices', 'stripe_payments'];

    /**
     * @return array{
     *     photos: int,
     *     storage_bytes: int,
     *     galleries: int,
     *     contracts: int,
     *     users: int,
     *     paid_users: int,
     *     lifetime_users: int,
     *     free_users: int,
     *     invoices: int,
     *     invoice_teams: int,
     *     stripe_payments: int,
     *     stripe_accounts: int,
     *     accounts: int,
     *     avg_photos_per_user: float,
     *     avg_photos_per_paid_user: float,
     *     avg_photos_per_lifetime_user: float,
     *     avg_photos_per_free_user: float,
     *     computed_at: string
     * }
     */
    public function snapshot(): array
    {
        return Cache::flexible(
            self::CACHE_PREFIX.':snapshot',
            [3600, 604800],
            fn (): array => $this->buildSnapshot(),
        );
    }

    /**
     * @return array{
     *     photos: int,
     *     galleries: int,
     *     contracts: int,
     *     users: int,
     *     paid_users: int,
     *     lifetime_users: int,
     *     invoices: int,
     *     invoice_teams: int,
     *     stripe_payments: int,
     *     computed_at: string
     * }
     */
    public function range(?CarbonInterface $from, CarbonInterface $to): array
    {
        $key = $this->rangeCacheKey($from, $to);
        $callback = fn (): array => $this->buildRange($from, $to);

        // Completed days are immutable for reporting purposes, so keep their
        // results indefinitely instead of recalculating historical ranges.
        if ($from !== null && $to->lt(now()->startOfDay())) {
            return Cache::rememberForever($key, $callback);
        }

        return Cache::flexible($key, [900, 86400], $callback);
    }

    /**
     * Monthly values for a single dashboard metric.
     *
     * @return list<array{month: string, label: string, value: int}>
     */
    public function monthlySeries(string $metric, CarbonInterface $from, CarbonInterface $to): array
    {
        $key = self::CACHE_PREFIX.":monthly:{$metric}:{$from->toDateString()}:{$to->toDateString()}";

        return Cache::flexible(
            $key,
            [900, 86400],
            fn (): array => $this->buildMonthlySeries($metric, $from, $to),
        );
    }

    /**
     * All chart series in one cache entry, to avoid one cache round trip per metric.
     *
     * @return array<string, list<array{month: string, label: string, value: int}>>
     */
    public function monthlySeriesForAll(CarbonInterface $from, CarbonInterface $to): array
    {
        $key = self::CACHE_PREFIX.":monthly:all:{$from->toDateString()}:{$to->toDateString()}";

        return Cache::flexible(
            $key,
            [900, 86400],
            fn (): array => collect(self::METRICS)
                ->mapWithKeys(fn (string $metric): array => [
                    $metric => $this->buildMonthlySeries($metric, $from, $to),
                ])
                ->all(),
        );
    }

    public function forgetSnapshot(): void
    {
        $this->forget(self::CACHE_PREFIX.':snapshot');
    }

    public function forgetRange(?CarbonInterface $from, CarbonInterface $to): void
    {
        $this->forget($this->rangeCacheKey($from, $to));
    }

    /**
     * @return array{
     *     photos: int,
     *     storage_bytes: int,
     *     galleries: int,
     *     contracts: int,
     *     users: int,
     *     paid_users: int,
     *     lifetime_users: int,
     *     free_users: int,
     *     invoices: int,
     *     invoice_teams: int,
     *     stripe_payments: int,
     *     stripe_accounts: int,
     *     accounts: int,
     *     avg_photos_per_user: float,
     *     avg_photos_per_paid_user: float,
     *     avg_photos_per_lifetime_user: float,
     *     avg_photos_per_free_user: float,
     *     computed_at: string
     * }
     */
    private function buildSnapshot(): array
    {
        $photos = Photo::query()->count();
        $users = User::query()->count();

        $paidUsers = $this->paidTeamsQuery()->distinct()->count('user_id');
        $lifetimeUsers = $this->lifetimeTeamsQuery()->distinct()->count('user_id');
        $freeUsers = max(0, $users - $paidUsers - $lifetimeUsers);

        $paidPhotos = $this->photoCountForTeams($this->paidTeamsQuery());
        $lifetimePhotos = $this->photoCountForTeams($this->lifetimeTeamsQuery());
        $freePhotos = max(0, $photos - $paidPhotos - $lifetimePhotos);

        return [
            'photos' => $photos,
            'storage_bytes' => StorageUsageService::total(),
            'galleries' => Gallery::query()->count(),
            'contracts' => Contract::query()->count(),
            'users' => $users,
            'paid_users' => $paidUsers,
            'lifetime_users' => $lifetimeUsers,
            'free_users' => $freeUsers,
            'invoices' => $this->issuedInvoicesQuery()->count(),
            'invoice_teams' => $this->issuedInvoicesQuery()->distinct()->count('team_id'),
            'stripe_payments' => $this->stripePaymentsQuery()->count(),
            'stripe_accounts' => $this->stripeAccountsQuery()->count(),
            'accounts' => Team::query()->where('personal_team', true)->count(),
            'avg_photos_per_user' => $this->average($photos, $users),
            'avg_photos_per_paid_user' => $this->average($paidPhotos, $paidUsers),
            'avg_photos_per_lifetime_user' => $this->average($lifetimePhotos, $lifetimeUsers),
            'avg_photos_per_free_user' => $this->average($freePhotos, $freeUsers),
            'computed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *     photos: int,
     *     galleries: int,
     *     contracts: int,
     *     users: int,
     *     paid_users: int,
     *     lifetime_users: int,
     *     invoices: int,
     *     invoice_teams: int,
     *     stripe_payments: int,
     *     computed_at: string
     * }
     */
    private function buildRange(?CarbonInterface $from, CarbonInterface $to): array
    {
        return [
            'photos' => $this->countCreatedInRange(Photo::query(), $from, $to),
            'galleries' => $this->countCreatedInRange(Gallery::query(), $from, $to),
            'contracts' => $this->countCreatedInRange(Contract::query(), $from, $to),
            'users' => $this->countCreatedInRange(User::query(), $from, $to),
            'paid_users' => Team::query()
                ->where('personal_team', true)
                ->whereHas('subscriptions', function (Builder $query) use ($from, $to): void {
                    $query->where('type', 'default')
                        ->when($from, fn (Builder $query) => $query->where('created_at', '>=', $from))
                        ->where('created_at', '<=', $to);
                })
                ->distinct()
                ->count('user_id'),
            'lifetime_users' => Team::query()
                ->where('personal_team', true)
                ->whereNotNull('lifetime_at')
                ->when($from, fn (Builder $query) => $query->where('lifetime_at', '>=', $from))
                ->where('lifetime_at', '<=', $to)
                ->distinct()
                ->count('user_id'),
            'invoices' => $this->issuedInvoicesQuery()
                ->when($from, fn (Builder $query) => $query->where('issued_at', '>=', $from))
                ->where('issued_at', '<=', $to)
                ->count(),
            'invoice_teams' => $this->issuedInvoicesQuery()
                ->when($from, fn (Builder $query) => $query->where('issued_at', '>=', $from))
                ->where('issued_at', '<=', $to)
                ->distinct()
                ->count('team_id'),
            'stripe_payments' => $this->stripePaymentsQuery()
                ->when($from, fn (Builder $query) => $query->where('completed_at', '>=', $from))
                ->where('completed_at', '<=', $to)
                ->count(),
            'computed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return list<array{month: string, label: string, value: int}>
     */
    private function buildMonthlySeries(string $metric, CarbonInterface $from, CarbonInterface $to): array
    {
        $from = $from->copy()->startOfMonth();
        $lastMonth = $to->copy()->startOfMonth();

        $totals = match ($metric) {
            'photos' => $this->monthlyCount(Photo::query(), 'created_at', $from, $to),
            'galleries' => $this->monthlyCount(Gallery::query(), 'created_at', $from, $to),
            'contracts' => $this->monthlyCount(Contract::query(), 'created_at', $from, $to),
            'users' => $this->monthlyCount(User::query(), 'created_at', $from, $to),
            'paid_users' => $this->monthlyPaidUsers($from, $to),
            'lifetime_users' => $this->monthlyLifetimeUsers($from, $to),
            'invoices' => $this->monthlyCount($this->issuedInvoicesQuery(), 'issued_at', $from, $to),
            'stripe_payments' => $this->monthlyCount($this->stripePaymentsQuery(), 'completed_at', $from, $to),
            default => throw new InvalidArgumentException("Unknown usage metric [{$metric}]."),
        };

        $series = [];
        $cursor = $from->copy();

        while ($cursor->lte($lastMonth)) {
            $month = $cursor->format('Y-m');

            $series[] = [
                'month' => $month,
                'label' => $cursor->copy()->toDateString(),
                'value' => (int) ($totals[$month] ?? 0),
            ];

            $cursor->addMonthNoOverflow();
        }

        return $series;
    }

    /**
     * @return array<string, int>
     */
    private function monthlyCount(Builder $query, string $column, CarbonInterface $from, CarbonInterface $to): array
    {
        return $query
            ->whereBetween($column, [$from, $to])
            ->selectRaw($this->monthExpression($column).' as month')
            ->selectRaw('count(*) as total')
            ->groupBy('month')
            ->pluck('total', 'month')
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function monthlyPaidUsers(CarbonInterface $from, CarbonInterface $to): array
    {
        return Team::query()
            ->join('subscriptions', 'subscriptions.team_id', '=', 'teams.id')
            ->where('teams.personal_team', true)
            ->where('subscriptions.type', 'default')
            ->whereBetween('subscriptions.created_at', [$from, $to])
            ->selectRaw($this->monthExpression('subscriptions.created_at').' as month')
            ->selectRaw('count(distinct teams.user_id) as total')
            ->groupBy('month')
            ->pluck('total', 'month')
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function monthlyLifetimeUsers(CarbonInterface $from, CarbonInterface $to): array
    {
        return Team::query()
            ->where('personal_team', true)
            ->whereNotNull('lifetime_at')
            ->whereBetween('lifetime_at', [$from, $to])
            ->selectRaw($this->monthExpression('lifetime_at').' as month')
            ->selectRaw('count(distinct teams.user_id) as total')
            ->groupBy('month')
            ->pluck('total', 'month')
            ->all();
    }

    /**
     * Portable month bucket expression for the supported database drivers.
     */
    private function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    private function issuedInvoicesQuery(): Builder
    {
        return Invoice::query()->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_RECTIFIED]);
    }

    /**
     * Completed payments collected through Stripe, excluding local fake payments.
     */
    private function stripePaymentsQuery(): Builder
    {
        return Payment::query()
            ->whereNotNull('completed_at')
            ->whereNotNull('stripe_payment_intent_id')
            ->where('stripe_payment_intent_id', 'not like', 'fake_%');
    }

    /**
     * Personal teams whose current Stripe mode has completed onboarding.
     */
    private function stripeAccountsQuery(): Builder
    {
        return Team::query()
            ->where('personal_team', true)
            ->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->where('stripe_test_mode', false)
                        ->where('stripe_onboarded', true);
                })->orWhere(function (Builder $query): void {
                    $query->where('stripe_test_mode', true)
                        ->where('stripe_test_onboarded', true);
                });
            });
    }

    private function paidTeamsQuery(): Builder
    {
        return Team::query()
            ->where('personal_team', true)
            ->whereHas('subscriptions', fn (Builder $query) => $query->where('type', 'default')->active());
    }

    /**
     * Lifetime teams without an active subscription, so paid and lifetime
     * buckets never overlap.
     */
    private function lifetimeTeamsQuery(): Builder
    {
        return Team::query()
            ->where('personal_team', true)
            ->whereNotNull('lifetime_at')
            ->whereDoesntHave('subscriptions', fn (Builder $query) => $query->where('type', 'default')->active());
    }

    private function countCreatedInRange(Builder $query, ?CarbonInterface $from, CarbonInterface $to): int
    {
        return $query
            ->when($from, fn (Builder $query) => $query->where('created_at', '>=', $from))
            ->where('created_at', '<=', $to)
            ->count();
    }

    private function photoCountForTeams(Builder $teamQuery): int
    {
        return Photo::query()
            ->whereHas('gallery', fn (Builder $query) => $query->whereIn('team_id', $teamQuery->select('teams.id')))
            ->count();
    }

    private function average(int $photos, int $users): float
    {
        return $users > 0 ? round($photos / $users, 1) : 0.0;
    }

    private function rangeCacheKey(?CarbonInterface $from, CarbonInterface $to): string
    {
        return self::CACHE_PREFIX.':range:'.($from?->toDateString() ?? 'all').':'.$to->toDateString();
    }

    /**
     * Cache::flexible keeps a companion timestamp key; clear both on refresh.
     */
    private function forget(string $key): void
    {
        Cache::forget($key);
        Cache::forget("illuminate:cache:flexible:created:{$key}");
    }
}
