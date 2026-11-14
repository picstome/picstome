<?php

use App\Models\Team;
use App\Models\User;
use App\Services\ScheduledPlanChangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'plans.currency' => 'EUR',
        'plans.tiers.100gb.price_ids.monthly' => 'price_100_monthly',
        'plans.tiers.100gb.price_ids.annual' => 'price_100_annual',
        'promotions.black_friday.enabled' => true,
        'promotions.black_friday.force' => true,
        'promotions.black_friday.coupons.annual' => 'coupon_annual_20',
    ]);
});

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

function fakeScheduleStripe(): object
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

            if (str_ends_with($absUrl, '/release')) {
                return [json_encode(['id' => 'sub_sched_test', 'object' => 'subscription_schedule', 'status' => 'released']), 200, []];
            }

            if (str_contains($absUrl, '/v1/subscription_schedules')) {
                return [json_encode([
                    'id' => 'sub_sched_test',
                    'object' => 'subscription_schedule',
                    'status' => 'active',
                    'phases' => [
                        [
                            'start_date' => 1764547200,
                            'end_date' => 1767139200,
                            'items' => [
                                ['price' => ['id' => 'price_100_monthly'], 'quantity' => 1],
                            ],
                            'discounts' => [],
                        ],
                    ],
                ]), 200, []];
            }

            return [json_encode(['id' => 'obj_test', 'object' => 'object']), 200, []];
        }
    };

    ApiRequestor::setHttpClient($client);

    return $client;
}

function scheduledTeam(): Team
{
    $user = User::factory()->create();
    $team = Team::factory()->create([
        'user_id' => $user->id,
        'personal_team' => true,
        'stripe_id' => 'cus_test',
        'custom_storage_limit' => 107374182400,
    ]);

    $user->switchTeam($team);

    DB::table('subscriptions')->insert([
        'team_id' => $team->id,
        'type' => 'default',
        'stripe_id' => 'sub_monthly',
        'stripe_status' => 'active',
        'stripe_price' => 'price_100_monthly',
        'quantity' => 1,
        'billing_interval' => 'month',
        'ends_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $team->fresh();
}

it('schedules an annual phase at the end of the current period', function () {
    $client = fakeScheduleStripe();
    $team = scheduledTeam();

    app(ScheduledPlanChangeService::class)->schedule($team, '100gb');

    $subscription = $team->subscription('default')->fresh();

    expect($subscription->stripe_schedule_id)->toBe('sub_sched_test')
        ->and($subscription->scheduled_tier)->toBe('100gb')
        ->and($subscription->scheduled_price_id)->toBe('price_100_annual')
        ->and($subscription->scheduled_change_at)->not->toBeNull();

    $update = collect($client->requests)
        ->first(fn (array $request) => str_contains($request['url'], '/v1/subscription_schedules/sub_sched_test'));

    expect($update['params']['phases'][1]['items'][0]['price'])->toBe('price_100_annual')
        ->and($update['params']['phases'][1]['discounts'])->toBe([['coupon' => 'coupon_annual_20']]);
});

it('clears the scheduled change when it is cancelled', function () {
    fakeScheduleStripe();
    $team = scheduledTeam();

    app(ScheduledPlanChangeService::class)->schedule($team, '100gb');
    app(ScheduledPlanChangeService::class)->cancel($team->fresh());

    $subscription = $team->subscription('default')->fresh();

    expect($subscription->stripe_schedule_id)->toBeNull()
        ->and($subscription->scheduled_tier)->toBeNull()
        ->and($subscription->scheduled_price_id)->toBeNull()
        ->and($subscription->scheduled_change_at)->toBeNull();
});
