<?php

namespace App\Services;

use App\Models\CalculatorState;
use App\Models\Team;
use Illuminate\Support\Str;

/**
 * Stores and calculates photography session pricing.
 *
 * The calculator is a single document per team. Monthly costs and monthly
 * time are allocated across the expected number of sessions. Equipment is
 * depreciated after its resale value. VAT is added to the invoice, while IRPF
 * is withheld from the base price instead of being added on top of it.
 */
class CalculatorService
{
    private const ITEM_CATEGORIES = [
        'fixed_expenses',
        'session_expenses',
        'equipment',
        'session_tasks',
        'monthly_tasks',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $defaults = config('calculator.defaults');

        $state = ['settings' => $defaults['settings']];

        foreach (self::ITEM_CATEGORIES as $category) {
            $state[$category] = array_map(function (array $item): array {
                return [
                    'id' => (string) Str::uuid(),
                    'name' => __($item['name']),
                    ...array_diff_key($item, ['name' => true]),
                ];
            }, $defaults[$category]);
        }

        return self::normalize($state);
    }

    /**
     * @return array<string, mixed>
     */
    public static function forTeam(Team $team): array
    {
        $record = $team->calculatorState()->first();

        if (! $record) {
            $record = $team->calculatorState()->create([
                'schema_version' => CalculatorState::SCHEMA_VERSION,
                'state' => self::defaults(),
            ]);
        } elseif ($record->schema_version !== CalculatorState::SCHEMA_VERSION) {
            $record->update([
                'schema_version' => CalculatorState::SCHEMA_VERSION,
                'state' => self::normalize($record->state ?? []),
            ]);
        }

        return self::normalize($record->state ?? []);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function save(Team $team, array $state): array
    {
        $normalized = self::normalize($state);

        $team->calculatorState()->updateOrCreate([], [
            'schema_version' => CalculatorState::SCHEMA_VERSION,
            'state' => $normalized,
        ]);

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public static function reset(Team $team): array
    {
        $current = self::forTeam($team);
        $defaults = self::defaults();
        $defaults['settings']['guide_dismissed_at'] = $current['settings']['guide_dismissed_at'];

        return self::save($team, $defaults);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function normalize(array $state): array
    {
        $settings = is_array($state['settings'] ?? null) ? $state['settings'] : [];

        return [
            'settings' => [
                'hourly_rate' => self::money($settings['hourly_rate'] ?? 50),
                'sessions_per_month' => max(1, min(1000, (int) ($settings['sessions_per_month'] ?? 10))),
                'vat_percent' => self::percent($settings['vat_percent'] ?? 21),
                'income_tax_percent' => self::percent($settings['income_tax_percent'] ?? 15),
                'income_tax_withholding' => filter_var(
                    $settings['income_tax_withholding'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                ),
                'guide_dismissed_at' => is_string($settings['guide_dismissed_at'] ?? null)
                    ? $settings['guide_dismissed_at']
                    : null,
            ],
            'fixed_expenses' => self::normalizeAmountItems($state['fixed_expenses'] ?? []),
            'session_expenses' => self::normalizeAmountItems($state['session_expenses'] ?? []),
            'equipment' => self::normalizeEquipment($state['equipment'] ?? []),
            'session_tasks' => self::normalizeHourItems($state['session_tasks'] ?? []),
            'monthly_tasks' => self::normalizeHourItems($state['monthly_tasks'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, float|int|bool>
     */
    public static function summary(array $state): array
    {
        $state = self::normalize($state);
        $settings = $state['settings'];
        $sessions = max(1, $settings['sessions_per_month']);

        $fixedTotal = array_sum(array_column($state['fixed_expenses'], 'amount'));
        $sessionTotal = array_sum(array_column($state['session_expenses'], 'amount'));
        $equipmentTotal = array_sum(array_column($state['equipment'], 'price'));

        $monthlyDepreciation = array_sum(array_map(
            fn (array $item): float => max(0, $item['price'] - $item['resale_value'])
                / max(1, $item['useful_life_years'])
                / 12,
            $state['equipment']
        ));

        $sessionHours = array_sum(array_column($state['session_tasks'], 'hours'));
        $monthlyHours = array_sum(array_column($state['monthly_tasks'], 'hours'));

        $fixedPerSession = $fixedTotal / $sessions;
        $depreciationPerSession = $monthlyDepreciation / $sessions;
        $sessionTimeCost = $sessionHours * $settings['hourly_rate'];
        $monthlyTimeCost = $monthlyHours * $settings['hourly_rate'];
        $monthlyTimePerSession = $monthlyTimeCost / $sessions;
        $totalTimePerSession = $sessionHours + ($monthlyHours / $sessions);

        $baseCost = $fixedPerSession + $sessionTotal + $depreciationPerSession;
        $timeCost = $sessionTimeCost + $monthlyTimePerSession;
        $basePrice = $baseCost + $timeCost;
        $vatAmount = $basePrice * ($settings['vat_percent'] / 100);
        $invoiceTotal = $basePrice + $vatAmount;
        $incomeTaxWithholding = $settings['income_tax_withholding'];
        $incomeTaxWithheld = $incomeTaxWithholding
            ? $basePrice * ($settings['income_tax_percent'] / 100)
            : 0.0;
        $clientPays = $invoiceTotal - $incomeTaxWithheld;
        $netReceived = $basePrice - $incomeTaxWithheld;

        return [
            'fixed_total' => round($fixedTotal, 2),
            'fixed_per_session' => round($fixedPerSession, 2),
            'session_total' => round($sessionTotal, 2),
            'equipment_total' => round($equipmentTotal, 2),
            'monthly_depreciation' => round($monthlyDepreciation, 2),
            'depreciation_per_session' => round($depreciationPerSession, 2),
            'session_hours' => round($sessionHours, 2),
            'session_time_cost' => round($sessionTimeCost, 2),
            'monthly_hours' => round($monthlyHours, 2),
            'monthly_time_cost' => round($monthlyTimeCost, 2),
            'monthly_time_per_session' => round($monthlyTimePerSession, 2),
            'total_time_per_session' => round($totalTimePerSession, 2),
            'base_cost' => round($baseCost, 2),
            'time_cost' => round($timeCost, 2),
            'base_price' => round($basePrice, 2),
            'vat_percent' => $settings['vat_percent'],
            'vat_amount' => round($vatAmount, 2),
            'invoice_total' => round($invoiceTotal, 2),
            'income_tax_percent' => $settings['income_tax_percent'],
            'income_tax_withholding' => $incomeTaxWithholding,
            'income_tax_withheld' => round($incomeTaxWithheld, 2),
            'client_pays' => round($clientPays, 2),
            'net_received' => round($netReceived, 2),
            'effective_gross_hourly' => $totalTimePerSession > 0
                ? round($basePrice / $totalTimePerSession, 2)
                : 0.0,
            'effective_net_hourly' => $totalTimePerSession > 0
                ? round($netReceived / $totalTimePerSession, 2)
                : 0.0,
        ];
    }

    /**
     * @return array<int, array{id: string, name: string, amount: float}>
     */
    private static function normalizeAmountItems(mixed $items): array
    {
        return self::mapItems($items, fn (array $item, string $id): array => [
            'id' => $id,
            'name' => self::name($item['name'] ?? ''),
            'amount' => self::money($item['amount'] ?? 0),
        ]);
    }

    /**
     * @return array<int, array{id: string, name: string, price: float, resale_value: float, useful_life_years: int}>
     */
    private static function normalizeEquipment(mixed $items): array
    {
        return self::mapItems($items, function (array $item, string $id): array {
            $price = self::money($item['price'] ?? 0);
            $resale = min($price, self::money($item['resale_value'] ?? 0));

            return [
                'id' => $id,
                'name' => self::name($item['name'] ?? ''),
                'price' => $price,
                'resale_value' => $resale,
                'useful_life_years' => max(1, min(50, (int) ($item['useful_life_years'] ?? 5))),
            ];
        });
    }

    /**
     * @return array<int, array{id: string, name: string, hours: float}>
     */
    private static function normalizeHourItems(mixed $items): array
    {
        return self::mapItems($items, fn (array $item, string $id): array => [
            'id' => $id,
            'name' => self::name($item['name'] ?? ''),
            'hours' => self::money($item['hours'] ?? 0),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function mapItems(mixed $items, callable $callback): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];
        $seenIds = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = is_string($item['id'] ?? null) && $item['id'] !== ''
                ? $item['id']
                : (string) Str::uuid();

            if (isset($seenIds[$id])) {
                $id = (string) Str::uuid();
            }

            $seenIds[$id] = true;
            $normalized[] = $callback($item, $id);
        }

        return $normalized;
    }

    private static function name(mixed $value): string
    {
        return mb_substr(trim((string) $value), 0, 255);
    }

    private static function money(mixed $value): float
    {
        return round(max(0, (float) $value), 2);
    }

    private static function percent(mixed $value): float
    {
        return round(max(0, min(100, (float) $value)), 2);
    }
}
