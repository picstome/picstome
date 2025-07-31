<?php

use App\Listeners\StripeEventListener;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Events\WebhookReceived;

uses(RefreshDatabase::class);

it('grants unlimited storage to the team after a successful invoice payment', function () {
    $team = Team::factory()->create([
        'stripe_id' => 'cus_test123',
        'custom_storage_limit' => 12345,
    ]);

    expect($team->fresh()->has_unlimited_storage)->toBeFalse();

    $payload = [
        'type' => 'invoice.payment_succeeded',
        'data' => [
            'object' => [
                'customer' => 'cus_test123',
            ],
        ],
    ];

    $event = new WebhookReceived($payload);
    $listener = new StripeEventListener;
    $listener->handle($event);

    expect($team->fresh()->has_unlimited_storage)->toBeTrue();
});
