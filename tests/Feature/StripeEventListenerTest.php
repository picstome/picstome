<?php

use App\Models\Team;
use Laravel\Cashier\Events\WebhookReceived;
use App\Listeners\StripeEventListener;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('removes the custom storage limit from the team when a successful invoice payment is received', function () {
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

    expect($team->fresh()->custom_storage_limit)->toBeNull();
});
