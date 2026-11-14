<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Events\WebhookHandled;

uses(RefreshDatabase::class);

function billingIntervalTeam(): Team
{
    $user = User::factory()->create();
    $team = Team::factory()->create([
        'user_id' => $user->id,
        'personal_team' => true,
        'stripe_id' => 'cus_test',
    ]);

    DB::table('subscriptions')->insert([
        'team_id' => $team->id,
        'type' => 'default',
        'stripe_id' => 'sub_test',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly',
        'quantity' => 1,
        'billing_interval' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $team->fresh();
}

it('stores the billing interval after Cashier handled the subscription', function () {
    $team = billingIntervalTeam();

    event(new WebhookHandled([
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [
                'id' => 'sub_test',
                'current_period_end' => now()->addMonth()->timestamp,
                'items' => [
                    'data' => [
                        ['price' => ['id' => 'price_monthly', 'recurring' => ['interval' => 'month']]],
                    ],
                ],
            ],
        ],
    ]));

    expect($team->subscription('default')->fresh()->billing_interval)->toBe('month')
        ->and($team->subscription('default')->fresh()->current_period_end)->not->toBeNull();
});

it('clears a scheduled change when the annual phase starts', function () {
    $team = billingIntervalTeam();

    $subscription = $team->subscription('default');
    $subscription->forceFill([
        'billing_interval' => 'month',
        'stripe_schedule_id' => 'sub_sched_test',
        'scheduled_tier' => '100gb',
        'scheduled_price_id' => 'price_annual',
        'scheduled_change_at' => now()->addDay(),
    ])->save();

    event(new WebhookHandled([
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => 'sub_test',
                'items' => [
                    'data' => [
                        ['price' => ['id' => 'price_annual', 'recurring' => ['interval' => 'year']]],
                    ],
                ],
            ],
        ],
    ]));

    $subscription->refresh();

    expect($subscription->billing_interval)->toBe('year')
        ->and($subscription->stripe_schedule_id)->toBeNull()
        ->and($subscription->scheduled_tier)->toBeNull()
        ->and($subscription->scheduled_price_id)->toBeNull()
        ->and($subscription->scheduled_change_at)->toBeNull();
});
