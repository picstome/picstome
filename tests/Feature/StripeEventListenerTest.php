<?php

use App\Listeners\StripeEventListener;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Events\WebhookReceived;

uses(RefreshDatabase::class);

it('sets storage limit based on price ID when subscription is created', function () {
    $priceMap = [
        'price_100gb' => 107374182400,
        'price_250gb' => 268435456000,
        'price_1000gb' => 1073741824000,
    ];

    config()->set('picstome.storage_limits_by_price', $priceMap);

    $team = Team::factory()->create([
        'stripe_id' => 'cus_test123',
        'custom_storage_limit' => 12345,
        'monthly_invoice_limit' => 10,
    ]);

    $payload = [
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [
                'customer' => 'cus_test123',
                'items' => [
                    'data' => [
                        ['price' => ['id' => 'price_250gb']],
                    ],
                ],
            ],
        ],
    ];

    $event = new WebhookReceived($payload);
    $listener = new StripeEventListener;
    $listener->handle($event);

    expect($team->fresh()->custom_storage_limit)->toBe(268435456000);
    expect($team->fresh()->monthly_contract_limit)->toBeNull();
    expect($team->fresh()->monthly_invoice_limit)->toBeNull();
});

it('falls back to 1TB when price ID is not in the map', function () {
    $priceMap = [
        'price_100gb' => 107374182400,
        'price_250gb' => 268435456000,
    ];

    config()->set('picstome.storage_limits_by_price', $priceMap);

    $team = Team::factory()->create([
        'stripe_id' => 'cus_test123',
        'custom_storage_limit' => 12345,
    ]);

    $payload = [
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [
                'customer' => 'cus_test123',
                'items' => [
                    'data' => [
                        ['price' => ['id' => 'price_unknown']],
                    ],
                ],
            ],
        ],
    ];

    $event = new WebhookReceived($payload);
    $listener = new StripeEventListener;
    $listener->handle($event);

    expect($team->fresh()->custom_storage_limit)->toBe(
        config('picstome.subscription_storage_limit')
    );
});

it('falls back to 1TB when no items in the payload', function () {
    $team = Team::factory()->create([
        'stripe_id' => 'cus_test123',
        'custom_storage_limit' => 12345,
    ]);

    $payload = [
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [
                'customer' => 'cus_test123',
            ],
        ],
    ];

    $event = new WebhookReceived($payload);
    $listener = new StripeEventListener;
    $listener->handle($event);

    expect($team->fresh()->custom_storage_limit)->toBe(
        config('picstome.subscription_storage_limit')
    );
    expect($team->fresh()->monthly_contract_limit)->toBeNull();
});

it('sets correct storage from subscription.upgraded webhook', function () {
    $priceMap = [
        'price_100gb' => 107374182400,
        'price_1000gb' => 1073741824000,
    ];

    config()->set('picstome.storage_limits_by_price', $priceMap);

    $team = Team::factory()->create([
        'stripe_id' => 'cus_test123',
        'custom_storage_limit' => 107374182400,
        'monthly_contract_limit' => null,
    ]);

    $payload = [
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'customer' => 'cus_test123',
                'items' => [
                    'data' => [
                        ['price' => ['id' => 'price_1000gb']],
                    ],
                ],
            ],
        ],
    ];

    $event = new WebhookReceived($payload);
    $listener = new StripeEventListener;
    $listener->handle($event);

    expect($team->fresh()->custom_storage_limit)->toBe(1073741824000);
    expect($team->fresh()->monthly_contract_limit)->toBeNull();
});

it('sets lifetime + storage limit from checkout.session.completed', function () {
    $priceMap = ['price_250gb' => 268435456000];

    config()->set('picstome.storage_limits_by_price', $priceMap);

    $team = Team::factory()->create([
        'stripe_id' => 'cus_test123',
        'lifetime_at' => null,
        'custom_storage_limit' => 12345,
    ]);

    $payload = [
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test123',
                'mode' => 'payment',
                'customer' => 'cus_test123',
                'payment_status' => 'paid',
            ],
        ],
    ];

    $listener = Mockery::mock(StripeEventListener::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $listener->shouldReceive('getCheckoutPriceId')->andReturn('price_250gb');

    $event = new WebhookReceived($payload);
    $listener->handle($event);

    expect($team->fresh()->lifetime_at)->not->toBeNull();
    expect($team->fresh()->custom_storage_limit)->toBe(268435456000);
});

it('sets lifetime + fallback storage for unknown price in checkout', function () {
    config()->set('picstome.storage_limits_by_price', []);

    $team = Team::factory()->create([
        'stripe_id' => 'cus_test123',
        'lifetime_at' => null,
        'custom_storage_limit' => 12345,
    ]);

    $payload = [
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test123',
                'mode' => 'payment',
                'customer' => 'cus_test123',
                'payment_status' => 'paid',
            ],
        ],
    ];

    $listener = Mockery::mock(StripeEventListener::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $listener->shouldReceive('getCheckoutPriceId')->andReturn(null);

    $event = new WebhookReceived($payload);
    $listener->handle($event);

    expect($team->fresh()->lifetime_at)->not->toBeNull();
    expect($team->fresh()->custom_storage_limit)->toBe(
        config('picstome.subscription_storage_limit')
    );
});

it('ignores checkout.session.completed with subscription mode', function () {
    $team = Team::factory()->create([
        'stripe_id' => 'cus_test123',
        'lifetime_at' => null,
        'custom_storage_limit' => 12345,
    ]);

    $payload = [
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test123',
                'mode' => 'subscription',
                'customer' => 'cus_test123',
                'payment_status' => 'paid',
            ],
        ],
    ];

    $event = new WebhookReceived($payload);
    (new StripeEventListener)->handle($event);

    expect($team->fresh()->lifetime_at)->toBeNull();
    expect($team->fresh()->custom_storage_limit)->toBe(12345);
});

it('does not update storage for non-matching customer on checkout', function () {
    $team = Team::factory()->create([
        'stripe_id' => 'cus_test123',
        'lifetime_at' => null,
        'custom_storage_limit' => 12345,
    ]);

    $payload = [
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test456',
                'mode' => 'payment',
                'customer' => 'cus_unknown_customer',
                'payment_status' => 'paid',
            ],
        ],
    ];

    $event = new WebhookReceived($payload);
    (new StripeEventListener)->handle($event);

    expect($team->fresh()->lifetime_at)->toBeNull();
    expect($team->fresh()->custom_storage_limit)->toBe(12345);
});

it('ignores checkout.session.completed with missing mode and status fields', function () {
    $team = Team::factory()->create([
        'stripe_id' => 'cus_test123',
        'lifetime_at' => null,
        'custom_storage_limit' => 12345,
    ]);

    $payload = [
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test123',
                'customer' => 'cus_test123',
            ],
        ],
    ];

    $event = new WebhookReceived($payload);
    (new StripeEventListener)->handle($event);

    expect($team->fresh()->lifetime_at)->toBeNull();
    expect($team->fresh()->custom_storage_limit)->toBe(12345);
});

it('ignores checkout.session.completed with unpaid status', function () {
    $team = Team::factory()->create([
        'stripe_id' => 'cus_test123',
        'lifetime_at' => null,
        'custom_storage_limit' => 12345,
    ]);

    $payload = [
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test123',
                'mode' => 'payment',
                'customer' => 'cus_test123',
                'payment_status' => 'unpaid',
            ],
        ],
    ];

    $event = new WebhookReceived($payload);
    (new StripeEventListener)->handle($event);

    expect($team->fresh()->lifetime_at)->toBeNull();
    expect($team->fresh()->custom_storage_limit)->toBe(12345);
});

it('resets storage limit to 1GB when subscription is deleted', function () {
    $team = Team::factory()->create([
        'stripe_id' => 'cus_test456',
        'custom_storage_limit' => config('picstome.subscription_storage_limit'),
        'monthly_invoice_limit' => 10,
    ]);

    expect($team->fresh()->custom_storage_limit)->toBe(
        config('picstome.subscription_storage_limit')
    );

    $payload = [
        'type' => 'customer.subscription.deleted',
        'data' => [
            'object' => [
                'customer' => 'cus_test456',
            ],
        ],
    ];

    $event = new WebhookReceived($payload);
    $listener = new StripeEventListener;
    $listener->handle($event);

    expect($team->fresh()->custom_storage_limit)->toBe(
        config('picstome.personal_team_storage_limit')
    );
    expect($team->fresh()->monthly_contract_limit)->toBe(
        config('picstome.personal_team_monthly_contract_limit')
    );
    expect($team->fresh()->monthly_invoice_limit)->toBe(
        config('picstome.personal_team_monthly_invoice_limit')
    );
});

it('does not reset lifetime flag when subscription is deleted', function () {
    $team = Team::factory()->create([
        'stripe_id' => 'cus_lifetime',
        'lifetime_at' => now()->subDay(),
        'custom_storage_limit' => config('picstome.subscription_storage_limit'),
    ]);

    $payload = [
        'type' => 'customer.subscription.deleted',
        'data' => [
            'object' => [
                'customer' => 'cus_lifetime',
            ],
        ],
    ];

    $event = new WebhookReceived($payload);
    $listener = new StripeEventListener;
    $listener->handle($event);

    expect($team->fresh()->lifetime_at)->not->toBeNull();
});

it('does not update storage limit for non-matching customer', function () {
    $team = Team::factory()->create([
        'stripe_id' => 'cus_test123',
        'custom_storage_limit' => 12345,
    ]);

    $payload = [
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [
                'customer' => 'cus_different_customer',
            ],
        ],
    ];

    $event = new WebhookReceived($payload);
    $listener = new StripeEventListener;
    $listener->handle($event);

    expect($team->fresh()->custom_storage_limit)->toBe(12345);
});

it('ignores unknown webhook events', function () {
    $team = Team::factory()->create([
        'stripe_id' => 'cus_test123',
        'custom_storage_limit' => 12345,
    ]);

    $payload = [
        'type' => 'customer.updated',
        'data' => [
            'object' => [
                'customer' => 'cus_test123',
            ],
        ],
    ];

    $event = new WebhookReceived($payload);
    $listener = new StripeEventListener;
    $listener->handle($event);

    expect($team->fresh()->custom_storage_limit)->toBe(12345);
});
