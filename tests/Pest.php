<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\Team;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Storage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Api\Webpage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature', '../resources/views');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Register a local test disk that does not support temporary URLs.
 *
 * @return array{disk: string, root: string}
 */
function fakeLocalDiskWithoutTemporaryUrls(): array
{
    $disk = 'local-no-temp-'.uniqid();
    $root = sys_get_temp_dir().'/picstome-test-'.uniqid();

    Storage::set($disk, Storage::build([
        'driver' => 'local',
        'root' => $root,
    ]));

    return ['disk' => $disk, 'root' => $root];
}

/**
 * Make a fake disk return predictable presigned URLs and capture the
 * arguments used to generate them.
 *
 * @return object{urls: array<int, string>, options: array<int, array<string, mixed>>, expirations: array<int, DateTimeInterface>}
 */
function fakeTemporaryUrls(string $disk = 's3'): object
{
    $captured = new class
    {
        /** @var array<int, string> */
        public array $urls = [];

        /** @var array<int, array<string, mixed>> */
        public array $options = [];

        /** @var array<int, DateTimeInterface> */
        public array $expirations = [];
    };

    Storage::disk($disk)->buildTemporaryUrlsUsing(function (string $path, DateTimeInterface $expiration, array $options) use ($captured) {
        $url = 'https://s3.test/'.$path;

        $captured->urls[] = $url;
        $captured->options[] = $options;
        $captured->expirations[] = $expiration;

        return $url;
    });

    return $captured;
}

/**
 * Build a draft invoice with the snapshots an issued invoice needs.
 *
 * @param  array<string, mixed>  $attributes
 */
function draftInvoice(Team $team, array $attributes = []): Invoice
{
    Storage::fake(config('picstome.disk'));

    $settings = InvoiceSetting::firstOrCreate(
        ['team_id' => $team->id],
        array_merge(config('invoices.defaults'), [
            'legal_name' => 'Acme Studio SL',
            'tax_id' => 'B12345678',
            'address' => 'Calle Mayor 1',
            'city' => 'Madrid',
            'state' => 'Madrid',
            'postal_code' => '28001',
            'country' => 'ES',
            'email' => 'billing@acme.test',
            'phone' => '+34 600 000 000',
        ])
    );

    $customer = Customer::firstOrCreate(
        ['team_id' => $team->id, 'tax_id' => 'B12345678'],
        [
            'name' => 'Acme Studio SL',
            'legal_name' => 'Acme Studio SL',
            'address' => 'Calle Mayor 1',
            'city' => 'Madrid',
            'postal_code' => '28001',
            'country' => 'ES',
        ]
    );

    $snapshots = app(InvoiceService::class);

    return Invoice::create(array_merge([
        'team_id' => $team->id,
        'customer_id' => $customer->id,
        'invoice_type' => Invoice::TYPE_FULL,
        'currency' => $settings->currency,
        'issue_date' => now()->toDateString(),
        'vat_percent' => 21,
        'subtotal' => 100,
        'base_amount' => 100,
        'vat_amount' => 21,
        'total' => 121,
        'amount_due' => 121,
        'invoice_data' => [
            'line_items' => [
                ['description' => 'Photo session', 'quantity' => 1, 'unit_price' => 100],
            ],
            'issuer' => $snapshots->issuerSnapshot($settings),
            'customer' => $snapshots->customerSnapshot($customer),
        ],
    ], $attributes));
}

/**
 * Scroll the browser page to the bottom and wait until the position is applied.
 *
 * Browser tests run headless and in parallel in CI, so a single scrollTo() plus
 * two animation frames can race with layout changes. Retry until the viewport
 * actually moves or the timeout is reached.
 *
 * @return int The final vertical scroll position.
 */
function scrollPageToBottom(Webpage|PendingAwaitablePage $page, int $timeoutMs = 5000): int
{
    /** @var array{scrollY: int, scrollHeight: int, innerHeight: int} $result */
    $result = $page->script(<<<JS
        new Promise((resolve) => {
            const startedAt = performance.now();
            const root = document.scrollingElement || document.documentElement;

            root.style.scrollBehavior = 'auto';

            const finish = () => resolve({
                scrollY: Math.round(window.scrollY),
                scrollHeight: Math.round(Math.max(root.scrollHeight, document.body.scrollHeight)),
                innerHeight: Math.round(window.innerHeight),
            });

            const attempt = () => {
                const maxScroll = Math.max(
                    0,
                    root.scrollHeight - window.innerHeight,
                    document.body.scrollHeight - window.innerHeight,
                );

                if (maxScroll > 0) {
                    root.scrollTop = maxScroll;
                    window.scrollTo(0, maxScroll);
                }

                requestAnimationFrame(() => requestAnimationFrame(() => {
                    if (window.scrollY > 0 || performance.now() - startedAt > {$timeoutMs}) {
                        finish();

                        return;
                    }

                    setTimeout(attempt, 50);
                }));
            };

            attempt();
        });
    JS);

    if ($result['scrollY'] <= 0) {
        throw new RuntimeException(sprintf(
            'The page did not scroll: scrollY=%d, scrollHeight=%d, viewport=%d.',
            $result['scrollY'],
            $result['scrollHeight'],
            $result['innerHeight'],
        ));
    }

    return $result['scrollY'];
}

/**
 * Wait until the gallery grid has at least the expected number of photo cards.
 *
 * Infinite-scroll requests are asynchronous and can race with a fixed wait,
 * especially when the browser suite runs in parallel.
 */
function waitForPhotoCardCount(Webpage|PendingAwaitablePage $page, int $expected, int $timeoutMs = 15000): int
{
    /** @var int $count */
    $count = $page->script(<<<JS
        new Promise((resolve) => {
            const expected = {$expected};
            const startedAt = performance.now();

            const check = () => {
                const count = document.querySelectorAll('[data-photo-card]').length;

                if (count >= expected || performance.now() - startedAt > {$timeoutMs}) {
                    resolve(count);

                    return;
                }

                setTimeout(check, 100);
            };

            check();
        });
    JS);

    if ($count < $expected) {
        throw new RuntimeException(sprintf(
            'Expected at least %d photo cards, found %d after %d ms.',
            $expected,
            $count,
            $timeoutMs,
        ));
    }

    return $count;
}

/**
 * Wait for the application's smooth scroll-to-top to finish.
 */
function waitForPageScrollTop(Webpage|PendingAwaitablePage $page, int $timeoutMs = 3000): void
{
    $deadline = microtime(true) + ($timeoutMs / 1000);

    do {
        if ($page->script('window.scrollY') < 2) {
            return;
        }

        usleep(50_000);
    } while (microtime(true) < $deadline);

    throw new RuntimeException("The page did not scroll to the top within {$timeoutMs} ms.");
}

/**
 * Poll a browser-side script until it returns a truthy value.
 *
 * Livewire, Alpine and Morph render asynchronously, so fixed waits are flaky
 * under CI load. The script must return a value; the helper resolves as soon
 * as that value is truthy and fails after the timeout otherwise.
 */
function waitForBrowserValue(Webpage|PendingAwaitablePage $page, string $script, int $timeoutMs = 5000): mixed
{
    $deadline = microtime(true) + ($timeoutMs / 1000);

    do {
        $value = $page->script("(function () {\n{$script}\n})()");

        if ($value) {
            return $value;
        }

        usleep(100_000);
    } while (microtime(true) < $deadline);

    throw new RuntimeException("The browser value was not available within {$timeoutMs} ms.");
}
