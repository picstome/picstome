<?php

use App\Listeners\StripeEventListener;
use App\Models\Gallery;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Events\WebhookReceived;

uses(RefreshDatabase::class);

it('gives an expiration date to private galleries when the subscription ends', function () {
    $team = Team::factory()->create([
        'stripe_id' => 'cus_cancel',
        'custom_storage_limit' => config('picstome.subscription_storage_limit'),
    ]);

    $recent = Gallery::factory()->for($team)->create([
        'created_at' => now()->subMonths(3),
        'expiration_date' => null,
    ]);

    $old = Gallery::factory()->for($team)->create([
        'created_at' => now()->subYears(2),
        'expiration_date' => null,
    ]);

    (new StripeEventListener)->handle(new WebhookReceived([
        'type' => 'customer.subscription.deleted',
        'data' => ['object' => ['customer' => 'cus_cancel']],
    ]));

    expect($recent->fresh()->expiration_date->toDateString())
        ->toBe($recent->created_at->copy()->addYear()->toDateString());

    // Older galleries get a month of grace instead of a date in the past.
    expect($old->fresh()->expiration_date->toDateString())
        ->toBe(now()->addMonth()->toDateString());
});

it('never expires public galleries when the subscription ends', function () {
    $team = Team::factory()->create([
        'stripe_id' => 'cus_cancel',
        'custom_storage_limit' => config('picstome.subscription_storage_limit'),
    ]);

    $public = Gallery::factory()->for($team)->public()->create([
        'expiration_date' => null,
    ]);

    (new StripeEventListener)->handle(new WebhookReceived([
        'type' => 'customer.subscription.deleted',
        'data' => ['object' => ['customer' => 'cus_cancel']],
    ]));

    expect($public->fresh()->expiration_date)->toBeNull();
});
