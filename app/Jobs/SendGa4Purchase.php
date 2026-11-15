<?php

namespace App\Jobs;

use App\Services\AnalyticsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Server-side backup for the GA4 purchase event.
 *
 * The browser fires the same purchase with the same transaction_id on the
 * success page, so GA4 deduplicates the pair. This job only helps when an
 * extension blocks the client-side tag.
 */
class SendGa4Purchase implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * @param  array{
     *     client_id: string,
     *     user_id?: string|null,
     *     transaction_id: string,
     *     value: float|int,
     *     currency: string,
     *     items?: list<array<string, mixed>>,
     *     coupon?: string|null
     * }  $purchase
     */
    public function __construct(public array $purchase) {}

    public function handle(AnalyticsService $analytics): void
    {
        if (! $analytics->enabled() || $analytics->measurementId() === null || $analytics->apiSecret() === null) {
            return;
        }

        if (empty($this->purchase['client_id']) || empty($this->purchase['transaction_id'])) {
            return;
        }

        $items = array_map(
            fn (array $item): array => array_filter($item, fn ($value): bool => $value !== null && $value !== ''),
            $this->purchase['items'] ?? [],
        );

        $response = Http::timeout(10)->post(
            'https://www.google-analytics.com/mp/collect?'.http_build_query([
                'measurement_id' => $analytics->measurementId(),
                'api_secret' => $analytics->apiSecret(),
            ]),
            [
                'client_id' => $this->purchase['client_id'],
                'user_id' => $this->purchase['user_id'] ?? null,
                'timestamp_micros' => (int) (microtime(true) * 1_000_000),
                'events' => [[
                    'name' => 'purchase',
                    'params' => array_filter([
                        'transaction_id' => $this->purchase['transaction_id'],
                        'value' => $this->purchase['value'],
                        'currency' => $this->purchase['currency'],
                        'items' => $items,
                        'coupon' => $this->purchase['coupon'] ?? null,
                        'engagement_time_msec' => 100,
                    ], fn ($value): bool => $value !== null),
                ]],
            ],
        );

        $response->throw();
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('GA4 Measurement Protocol purchase failed', [
            'transaction_id' => $this->purchase['transaction_id'] ?? null,
            'message' => $exception->getMessage(),
        ]);
    }
}
