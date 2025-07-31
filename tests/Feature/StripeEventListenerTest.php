<?php

use App\Models\Team;
use Laravel\Cashier\Events\WebhookReceived;
use App\Listeners\StripeEventListener;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it sets custom_storage_limit to null when invoice.payment_succeeded is received for matching team', function () {
    $team = Team::factory()->create([
        'stripe_id' => 'cus_test123',
        'custom_storage_limit' => 12345,
    ]);

    $payload = [
        'type' => 'invoice.payment_succeeded',
        'data' => [
            'object' => [
                'customer' => 'cus_test123',
            ],
        ],
    ];

    $event = new WebhookReceived($payload);
    $listener = new StripeEventListener();

    $listener->handle($event);

    expect($team->fresh()->has_unlimited_storage)->toBeTrue();
});
