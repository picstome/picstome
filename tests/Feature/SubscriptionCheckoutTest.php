<?php

use App\Models\Team;
use App\Models\User;
use App\Services\SubscriptionCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()->setLocale('es');

    config([
        'plans.currency' => 'EUR',
        'plans.tiers.100gb.price_ids.monthly' => 'price_100_monthly',
        'plans.tiers.100gb.price_ids.annual' => 'price_100_annual',
        'plans.tiers.100gb.price_ids.lifetime' => 'price_100_lifetime',
        'plans.tiers.250gb.price_ids.monthly' => 'price_250_monthly',
        'plans.tiers.250gb.price_ids.annual' => 'price_250_annual',
        'plans.tiers.1tb.price_ids.monthly' => 'price_1tb_monthly',
        'plans.tiers.1tb.price_ids.annual' => 'price_1tb_annual',
        'promotions.black_friday.enabled' => true,
        'promotions.black_friday.force' => true,
        'promotions.black_friday.coupons.monthly.100gb' => 'coupon_1eur_100',
        'promotions.black_friday.coupons.monthly.250gb' => 'coupon_1eur_250',
        'promotions.black_friday.coupons.monthly.1tb' => 'coupon_5eur_1tb',
        'promotions.black_friday.coupons.annual' => 'coupon_annual_20',
    ]);
});

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

function fakeCheckoutStripe(): object
{
    $client = new class implements ClientInterface
    {
        /** @var list<array{method: string, url: string, params: array<string, mixed>}> */
        public array $requests = [];

        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1')
        {
            $this->requests[] = [
                'method' => $method,
                'url' => $absUrl,
                'params' => $params,
            ];

            if (str_contains($absUrl, '/v1/customers')) {
                return [json_encode(['id' => 'cus_test', 'object' => 'customer']), 200, []];
            }

            if (str_contains($absUrl, '/v1/checkout/sessions')) {
                return [json_encode([
                    'id' => 'cs_test',
                    'object' => 'checkout.session',
                    'url' => 'https://checkout.stripe.test/cs_test',
                    'mode' => $params['mode'] ?? 'subscription',
                ]), 200, []];
            }

            return [json_encode(['id' => 'obj_test', 'object' => 'object']), 200, []];
        }
    };

    ApiRequestor::setHttpClient($client);

    return $client;
}

function checkoutTeam(): Team
{
    $user = User::factory()->create();
    $team = Team::factory()->create([
        'user_id' => $user->id,
        'personal_team' => true,
        'stripe_id' => 'cus_test',
        'custom_storage_limit' => 107374182400,
    ]);

    $user->switchTeam($team);

    return $team->fresh();
}

function checkoutSessionRequest(object $client): array
{
    return collect($client->requests)
        ->first(fn (array $request) => str_contains($request['url'], '/v1/checkout/sessions'));
}

it('creates a monthly checkout with the 1 euro coupon', function () {
    $client = fakeCheckoutStripe();
    $team = checkoutTeam();

    $checkout = app(SubscriptionCheckoutService::class)->checkout($team, '100gb', 'monthly');

    expect($checkout->url)->toBe('https://checkout.stripe.test/cs_test');

    $request = checkoutSessionRequest($client);

    expect($request['params']['mode'])->toBe('subscription')
        ->and($request['params']['discounts'])->toBe([['coupon' => 'coupon_1eur_100']])
        ->and($request['params']['line_items'][0]['price'])->toBe('price_100_monthly')
        ->and($request['params']['subscription_data']['metadata']['coupon'])->toBe('coupon_1eur_100')
        ->and($request['params']['subscription_data']['metadata']['promotion'])->toBe('black_friday_2026');
});

it('uses the 5 euro coupon for the 1 TB monthly plan', function () {
    $client = fakeCheckoutStripe();
    $team = checkoutTeam();

    app(SubscriptionCheckoutService::class)->checkout($team, '1tb', 'monthly');

    $request = checkoutSessionRequest($client);

    expect($request['params']['discounts'])->toBe([['coupon' => 'coupon_5eur_1tb']])
        ->and($request['params']['line_items'][0]['price'])->toBe('price_1tb_monthly');
});

it('uses the annual coupon for an annual checkout', function () {
    $client = fakeCheckoutStripe();
    $team = checkoutTeam();

    app(SubscriptionCheckoutService::class)->checkout($team, '250gb', 'annual');

    $request = checkoutSessionRequest($client);

    expect($request['params']['discounts'])->toBe([['coupon' => 'coupon_annual_20']])
        ->and($request['params']['line_items'][0]['price'])->toBe('price_250_annual');
});

it('does not add a discount when the campaign is over', function () {
    $this->travelTo('2026-12-02 00:00:00');
    config(['promotions.black_friday.force' => false]);

    $client = fakeCheckoutStripe();
    $team = checkoutTeam();

    app(SubscriptionCheckoutService::class)->checkout($team, '100gb', 'monthly');

    $request = checkoutSessionRequest($client);

    expect($request['params'])->not->toHaveKey('discounts');
});

it('refuses a new checkout for an existing monthly team', function () {
    fakeCheckoutStripe();
    $team = checkoutTeam();

    DB::table('subscriptions')->insert([
        'team_id' => $team->id,
        'type' => 'default',
        'stripe_id' => 'sub_monthly',
        'stripe_status' => 'active',
        'stripe_price' => 'price_100_monthly',
        'quantity' => 1,
        'billing_interval' => 'month',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => app(SubscriptionCheckoutService::class)->checkout($team->fresh(), '100gb', 'monthly'))
        ->toThrow(InvalidArgumentException::class);
});

it('creates a one-time checkout for the lifetime plan', function () {
    $client = fakeCheckoutStripe();
    $team = checkoutTeam();

    app(SubscriptionCheckoutService::class)->checkoutLifetime($team, '100gb');

    $request = checkoutSessionRequest($client);

    expect($request['params']['mode'])->toBe('payment')
        ->and($request['params']['discounts'])->toBe([['coupon' => 'coupon_annual_20']])
        ->and($request['params']['line_items'][0]['price'])->toBe('price_100_lifetime');
});
