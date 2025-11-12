<?php

use App\Listeners\StripeEventListener;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Events\WebhookReceived;

uses(RefreshDatabase::class);

it('sets 1TB storage limit when subscription is created', function () {
    $team = Team::factory()->create([
        'stripe_id' => 'cus_test123',
        'custom_storage_limit' => 12345,
    ]);

    expect($team->fresh()->custom_storage_limit)->toBe(12345);

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

it('resets storage limit to 1GB when subscription is deleted', function () {
    $team = Team::factory()->create([
        'stripe_id' => 'cus_test456',
        'custom_storage_limit' => config('picstome.subscription_storage_limit'),
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
                'customer' => 'cus_different_customer', // Different customer ID
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
