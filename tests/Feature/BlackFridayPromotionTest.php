<?php

use App\Models\Team;
use App\Models\User;
use App\Services\BlackFridayPromotion;
use App\Services\PlanCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'promotions.black_friday.enabled' => true,
        'promotions.black_friday.force' => false,
        'promotions.black_friday.starts_at' => '2026-11-16 00:00:00',
        'promotions.black_friday.ends_at' => '2026-12-01 00:00:00',
        'promotions.black_friday.timezone' => 'Europe/Madrid',
        'promotions.black_friday.annual_discount_percent' => 20,
        'promotions.black_friday.first_month_prices.100gb' => 100,
        'promotions.black_friday.first_month_prices.250gb' => 100,
        'promotions.black_friday.first_month_prices.1tb' => 500,
        'promotions.black_friday.coupons.monthly.100gb' => 'coupon_1eur_100',
        'promotions.black_friday.coupons.monthly.250gb' => 'coupon_1eur_250',
        'promotions.black_friday.coupons.monthly.1tb' => 'coupon_5eur_1tb',
        'promotions.black_friday.coupons.annual' => 'coupon_annual_20',
    ]);
});

function promotionTeam(array $teamAttributes = []): Team
{
    $user = User::factory()->create();
    $team = Team::factory()->create([
        'user_id' => $user->id,
        'personal_team' => true,
        ...$teamAttributes,
    ]);

    $user->switchTeam($team);

    return $team->fresh();
}

function promotionSubscription(Team $team, array $attributes = []): void
{
    DB::table('subscriptions')->insert([
        'team_id' => $team->id,
        'type' => 'default',
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly',
        'quantity' => 1,
        'billing_interval' => 'month',
        'ends_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
        ...$attributes,
    ]);
}

it('is active from the start until one minute before the end', function () {
    $promotion = app(BlackFridayPromotion::class);

    $this->travelTo('2026-11-15 23:00:00');
    expect($promotion->isActive())->toBeTrue();

    $this->travelTo('2026-11-30 22:59:00');
    expect($promotion->isActive())->toBeTrue();

    $this->travelTo('2026-11-30 23:00:00');
    expect($promotion->isActive())->toBeFalse();
});

it('can be forced in testing before the start date', function () {
    config(['promotions.black_friday.force' => true]);
    $this->travelTo('2026-11-01 00:00:00');

    expect(app(BlackFridayPromotion::class)->isActive())->toBeTrue();
});

it('is disabled when the flag is off', function () {
    config(['promotions.black_friday.enabled' => false]);
    $this->travelTo('2026-11-20 00:00:00');

    expect(app(BlackFridayPromotion::class)->isActive())->toBeFalse();
});

it('classifies team states', function () {
    $promotion = app(BlackFridayPromotion::class);
    $this->travelTo('2026-11-20 00:00:00');

    $free = promotionTeam();
    expect($promotion->stateFor($free))->toBe('free')
        ->and($promotion->offerFor($free))->toBe('free');

    $monthly = promotionTeam();
    promotionSubscription($monthly);
    expect($promotion->stateFor($monthly->fresh()))->toBe('monthly')
        ->and($promotion->offerFor($monthly->fresh()))->toBe('monthly');

    $annual = promotionTeam();
    promotionSubscription($annual, ['billing_interval' => 'year']);
    expect($promotion->stateFor($annual->fresh()))->toBe('annual')
        ->and($promotion->offerFor($annual->fresh()))->toBeNull();

    $lifetime = promotionTeam(['lifetime_at' => now()]);
    expect($promotion->stateFor($lifetime))->toBe('lifetime')
        ->and($promotion->offerFor($lifetime))->toBeNull();

    $incomplete = promotionTeam();
    promotionSubscription($incomplete, ['stripe_status' => 'incomplete']);
    expect($promotion->stateFor($incomplete->fresh()))->toBe('billing');
});

it('maps the monthly and annual coupons', function () {
    $promotion = app(BlackFridayPromotion::class);

    expect($promotion->couponFor('100gb', 'monthly', 'free'))->toBe('coupon_1eur_100')
        ->and($promotion->couponFor('250gb', 'monthly', 'free'))->toBe('coupon_1eur_250')
        ->and($promotion->couponFor('1tb', 'monthly', 'free'))->toBe('coupon_5eur_1tb')
        ->and($promotion->couponFor('100gb', 'annual', 'free'))->toBe('coupon_annual_20')
        ->and($promotion->couponFor('100gb', 'annual', 'monthly'))->toBe('coupon_annual_20')
        ->and($promotion->couponFor('100gb', 'monthly', 'monthly'))->toBeNull();
});

it('calculates the first-month and annual promo prices', function () {
    $catalog = app(PlanCatalog::class);

    expect($catalog->firstMonthPriceCents('100gb'))->toBe(100)
        ->and($catalog->firstMonthPriceCents('250gb'))->toBe(100)
        ->and($catalog->firstMonthPriceCents('1tb'))->toBe(500)
        ->and($catalog->monthlyCouponAmountCents('100gb'))->toBe(600)
        ->and($catalog->monthlyCouponAmountCents('250gb'))->toBe(1400)
        ->and($catalog->monthlyCouponAmountCents('1tb'))->toBe(2500)
        ->and($catalog->annualPriceAfterDiscountCents('100gb'))->toBe(4800)
        ->and($catalog->annualPriceAfterDiscountCents('250gb'))->toBe(9600)
        ->and($catalog->annualPriceAfterDiscountCents('1tb'))->toBe(19200)
        ->and($catalog->lifetimePriceAfterDiscountCents('100gb'))->toBe(14320)
        ->and($catalog->lifetimePriceAfterDiscountCents('250gb'))->toBe(28720)
        ->and($catalog->lifetimePriceAfterDiscountCents('1tb'))->toBe(55920);
});
