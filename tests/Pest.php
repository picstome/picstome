<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\Team;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Storage;
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
