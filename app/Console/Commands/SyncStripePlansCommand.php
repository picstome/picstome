<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Laravel\Cashier\Cashier;
use Stripe\Coupon;
use Stripe\Exception\InvalidRequestException;
use Stripe\Price;
use Stripe\Product;
use Throwable;

/**
 * Creates or reconciles the subscription catalog and Black Friday coupons in
 * the Stripe mode used by the current keys.
 *
 * Product/pricing data lives in Stripe; this command makes the configured
 * amounts, intervals, tax behavior and lookup keys match config/plans.php.
 * It is idempotent and never deletes existing prices or coupons.
 */
class SyncStripePlansCommand extends Command
{
    protected $signature = 'stripe:sync-plans
                            {--dry-run : Show what would be created or updated}
                            {--force : Create or update the objects in Stripe}
                            {--json= : Optional path for the ID mapping JSON}';

    protected $description = 'Sync Picstome plans and Black Friday coupons with the current Stripe mode';

    public function handle(): int
    {
        if (! $this->option('dry-run') && ! $this->option('force')) {
            $this->error('Use --dry-run to inspect or --force to write.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $stripe = Cashier::stripe();
        $currency = strtolower((string) config('plans.currency', 'EUR'));

        try {
            $products = $this->productsByName($stripe);
        } catch (Throwable $exception) {
            $this->error("Could not read Stripe products: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $mapping = [
            'price_ids' => [],
            'coupons' => [],
        ];

        foreach (config('plans.tiers', []) as $tierKey => $tier) {
            $productName = (string) ($tier['stripe_product_name'] ?? $tier['name']);
            $product = $products[$productName] ?? null;

            if (! $product) {
                $this->line(sprintf('%s product %s', $dryRun ? '[dry-run] create' : 'Creating', $productName));

                if (! $dryRun) {
                    $product = $stripe->products->create([
                        'name' => $productName,
                        'description' => "Picstome Pro {$tier['name']}",
                        'metadata' => ['tier' => $tierKey],
                    ]);
                    $products[$productName] = $product;
                }
            }

            if (! $product) {
                // Dry-run only: keep resolving the remaining tiers without IDs.
                continue;
            }

            foreach (['monthly', 'annual', 'lifetime'] as $interval) {
                $amount = (int) ($tier['prices'][$interval] ?? 0);
                $lookupKey = (string) ($tier['lookup_keys'][$interval] ?? '');
                $existingPrices = $this->pricesForProduct($stripe, $product->id);
                $price = $this->findPrice($existingPrices, $lookupKey, $amount, $interval, $currency);

                if ($price && $this->priceMatches($price, $amount, $interval, $currency)) {
                    $this->line(sprintf('%s price %s/%s', $dryRun ? '[dry-run] keep' : 'Keeping', $tier['name'], $interval));

                    if (! $dryRun) {
                        $stripe->prices->update($price->id, [
                            'lookup_key' => $lookupKey,
                            'nickname' => "{$tier['name']} {$interval}",
                            'metadata' => $this->priceMetadata($tierKey, $interval, $tier),
                        ]);
                    }
                } else {
                    $this->line(sprintf('%s price %s/%s (%d %s)', $dryRun ? '[dry-run] create' : 'Creating', $tier['name'], $interval, $amount, strtoupper($currency)));

                    if (! $dryRun) {
                        $payload = [
                            'product' => $product->id,
                            'currency' => $currency,
                            'unit_amount' => $amount,
                            'tax_behavior' => 'inclusive',
                            'lookup_key' => $lookupKey,
                            'transfer_lookup_key' => true,
                            'nickname' => "{$tier['name']} {$interval}",
                            'metadata' => $this->priceMetadata($tierKey, $interval, $tier),
                        ];

                        if ($interval !== 'lifetime') {
                            $payload['recurring'] = [
                                'interval' => $interval === 'annual' ? 'year' : 'month',
                            ];
                        }

                        $price = $stripe->prices->create($payload);
                    }
                }

                if ($price) {
                    $mapping['price_ids'][$tierKey][$interval] = $price->id;
                }
            }

            if (! $dryRun && isset($mapping['price_ids'][$tierKey]['monthly'])) {
                $stripe->products->update($product->id, [
                    'default_price' => $mapping['price_ids'][$tierKey]['monthly'],
                ]);
            }

            $monthlyAmount = (int) ($tier['prices']['monthly'] ?? 0);
            $firstMonthAmount = (int) config("promotions.black_friday.first_month_prices.{$tierKey}", 0);
            $monthlyCouponId = $this->couponId($tierKey);
            $monthlyCoupon = $this->ensureCoupon(
                $stripe,
                $monthlyCouponId,
                [
                    'name' => "Black Friday 2026 – {$tier['name']} first month",
                    'duration' => 'once',
                    'currency' => $currency,
                    'amount_off' => max(0, $monthlyAmount - $firstMonthAmount),
                    'applies_to' => ['products' => [$product->id]],
                    'redeem_by' => $this->couponRedeemBy(),
                    'metadata' => [
                        'promotion' => 'black_friday_2026',
                        'tier' => $tierKey,
                        'offer' => 'first_month',
                    ],
                ],
                $dryRun,
            );

            if ($monthlyCoupon) {
                $mapping['coupons'][$tierKey] = $monthlyCoupon->id;
            }
        }

        $annualProductIds = [];

        foreach (config('plans.tiers', []) as $tier) {
            $product = $products[(string) ($tier['stripe_product_name'] ?? $tier['name'])] ?? null;

            if ($product) {
                $annualProductIds[] = $product->id;
            }
        }

        $annualCoupon = $this->ensureCoupon(
            $stripe,
            (string) (config('promotions.black_friday.coupons.annual') ?: 'BF26_ANNUAL20'),
            [
                'name' => 'Black Friday 20% annual + one-time',
                'duration' => 'once',
                'percent_off' => (int) config('promotions.black_friday.annual_discount_percent', 20),
                'applies_to' => ['products' => $annualProductIds],
                'metadata' => [
                    'promotion' => 'black_friday_2026',
                    'offer' => 'annual',
                ],
            ],
            $dryRun,
        );

        if ($annualCoupon) {
            $mapping['coupons']['annual'] = $annualCoupon->id;
        }

        $this->newLine();
        $this->info('Stripe plan mapping:');
        $this->line(json_encode($mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if (! $dryRun) {
            $jsonPath = $this->option('json') ?: storage_path('app/stripe-plans.json');
            File::put($jsonPath, json_encode($mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info("Mapping written to {$jsonPath}");
            $this->printEnv($mapping);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, Product>
     */
    private function productsByName($stripe): array
    {
        $products = [];
        $page = $stripe->products->all(['active' => true, 'limit' => 100]);

        foreach ($page->data as $product) {
            $products[$product->name] = $product;
        }

        return $products;
    }

    /**
     * @return list<Price>
     */
    private function pricesForProduct($stripe, string $productId): array
    {
        return $stripe->prices->all([
            'product' => $productId,
            'active' => true,
            'limit' => 100,
        ])->data;
    }

    /**
     * @param  list<Price>  $prices
     */
    private function findPrice(array $prices, string $lookupKey, int $amount, string $interval, string $currency): ?Price
    {
        foreach ($prices as $price) {
            if ($price->lookup_key === $lookupKey) {
                return $price;
            }
        }

        foreach ($prices as $price) {
            if ($this->priceMatches($price, $amount, $interval, $currency)) {
                return $price;
            }
        }

        return null;
    }

    private function priceMatches(Price $price, int $amount, string $interval, string $currency): bool
    {
        $priceInterval = $price->recurring->interval ?? null;
        $expectedInterval = match ($interval) {
            'monthly' => 'month',
            'annual' => 'year',
            default => null,
        };

        return $price->unit_amount === $amount
            && strtolower($price->currency) === $currency
            && $priceInterval === $expectedInterval
            && $price->tax_behavior === 'inclusive';
    }

    /**
     * @return array<string, string>
     */
    private function priceMetadata(string $tier, string $interval, array $tierConfig): array
    {
        return [
            'tier' => $tier,
            'interval' => $interval,
            'storage_limit' => (string) ($tierConfig['storage_limit'] ?? ''),
        ];
    }

    private function couponId(string $tier): string
    {
        return (string) (config("promotions.black_friday.coupons.monthly.{$tier}") ?: 'BF26_1EUR_'.strtoupper($tier));
    }

    private function couponRedeemBy(): int
    {
        $timezone = (string) config('promotions.black_friday.timezone', 'Europe/Madrid');

        return Carbon::parse((string) config('promotions.black_friday.ends_at', '2026-12-01 00:00:00'), $timezone)
            ->addDay()
            ->timestamp;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ensureCoupon($stripe, string $id, array $payload, bool $dryRun): ?Coupon
    {
        try {
            $coupon = $stripe->coupons->retrieve($id);

            if ($this->couponMatches($coupon, $payload)) {
                $this->line(sprintf('%s coupon %s', $dryRun ? '[dry-run] keep' : 'Keeping', $id));

                if (! $dryRun && ($coupon->name ?? null) !== ($payload['name'] ?? null)) {
                    $coupon = $stripe->coupons->update($id, ['name' => $payload['name']]);
                }

                return $coupon;
            }

            $newId = $id.'_'.now()->format('YmdHis');
            $this->warn("Coupon {$id} exists with different settings; creating {$newId}.");

            if ($dryRun) {
                return null;
            }

            return $stripe->coupons->create(['id' => $newId, ...$payload]);
        } catch (InvalidRequestException $exception) {
            if ($exception->getHttpStatus() !== 404) {
                throw $exception;
            }

            $this->line(sprintf('%s coupon %s', $dryRun ? '[dry-run] create' : 'Creating', $id));

            if ($dryRun) {
                return null;
            }

            return $stripe->coupons->create(['id' => $id, ...$payload]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function couponMatches(Coupon $coupon, array $payload): bool
    {
        if (($payload['percent_off'] ?? null) !== null) {
            return (float) $coupon->percent_off === (float) $payload['percent_off']
                && $coupon->duration === $payload['duration'];
        }

        return $coupon->amount_off === $payload['amount_off']
            && strtolower((string) $coupon->currency) === strtolower((string) $payload['currency'])
            && $coupon->duration === $payload['duration'];
    }

    /**
     * @param  array{price_ids: array<string, array<string, string>>, coupons: array<string, string>}  $mapping
     */
    private function printEnv(array $mapping): void
    {
        $this->newLine();
        $this->info('Environment values:');

        foreach ([
            '100gb' => '100GB',
            '250gb' => '250GB',
            '1tb' => '1TB',
        ] as $tier => $label) {
            foreach (['monthly' => 'MONTHLY', 'annual' => 'ANNUAL', 'lifetime' => 'LIFETIME'] as $interval => $suffix) {
                $priceId = $mapping['price_ids'][$tier][$interval] ?? null;

                if ($priceId) {
                    $this->line("STRIPE_{$label}_{$suffix}_PRICE={$priceId}");
                }
            }

            $couponId = $mapping['coupons'][$tier] ?? null;

            if ($couponId) {
                $this->line('BLACK_FRIDAY_COUPON_'.strtoupper($tier).'='.$couponId);
            }
        }

        if (isset($mapping['coupons']['annual'])) {
            $this->line('BLACK_FRIDAY_COUPON_ANNUAL='.$mapping['coupons']['annual']);
        }
    }
}
