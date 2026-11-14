<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'plans.currency' => 'EUR',
        'promotions.black_friday.enabled' => true,
        'promotions.black_friday.force' => true,
        'promotions.black_friday.starts_at' => '2026-11-16 00:00:00',
        'promotions.black_friday.ends_at' => '2026-12-01 00:00:00',
        'promotions.black_friday.timezone' => 'Europe/Madrid',
        'promotions.black_friday.coupons.monthly.100gb' => 'coupon_1eur_100',
        'promotions.black_friday.coupons.monthly.250gb' => 'coupon_1eur_250',
        'promotions.black_friday.coupons.monthly.1tb' => 'coupon_5eur_1tb',
        'promotions.black_friday.coupons.annual' => 'coupon_annual_20',
    ]);
});

function pricingPageUser(array $teamAttributes = []): User
{
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->personalTeam();

    $team->update([
        'custom_storage_limit' => 107374182400,
        ...$teamAttributes,
    ]);

    $user->switchTeam($team);

    return $user->fresh();
}

function pricingPageSubscription(User $user, array $attributes = []): void
{
    DB::table('subscriptions')->insert([
        'team_id' => $user->current_team_id,
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

it('shows the pricing page to guests', function () {
    get('/subscribe', ['Accept-Language' => 'es-ES'])
        ->assertOk()
        ->assertSee('Elige tu plan')
        ->assertSee('Empezar ahora');
});

it('redirects the old Black Friday landing to subscribe', function () {
    get('/black-friday')->assertRedirect('/subscribe');
});

it('shows the Black Friday offer to a free team', function () {
    $user = pricingPageUser();
    $user->update(['language' => 'es']);

    actingAs($user->fresh())
        ->get('/subscribe')
        ->assertOk()
        ->assertSee('Black Friday 2026')
        ->assertSee('Tu primer mes desde 1 € o un 20 % en anual')
        ->assertSee('1,00', false)
        ->assertSee('5,00', false)
        ->assertSee('IVA incluido')
        ->assertSee('Panel')
        ->assertDontSee('Gestionar suscripción')
        ->assertSeeInOrder(['Elige tu plan', 'Qué cambia con Pro'], false)
        ->assertDontSee('stripe-pricing-table');

    actingAs($user->fresh())
        ->get('/subscribe?interval=annual')
        ->assertOk()
        ->assertSee('48,00', false)
        ->assertSee('96,00', false)
        ->assertSee('192,00', false)
        ->assertSee('4,00', false)
        ->assertSee('Facturado anualmente');

    actingAs($user->fresh())
        ->get('/subscribe?interval=lifetime')
        ->assertOk()
        ->assertSee('143,20', false)
        ->assertSee('179,00', false)
        ->assertSee('20 % de descuento')
        ->assertSee('Pago único')
        ->assertSee('Las galerías caducan en un año');
});

it('shows annual upgrade copy to a monthly team', function () {
    $user = pricingPageUser();
    $user->update(['language' => 'es']);
    pricingPageSubscription($user);

    actingAs($user->fresh())
        ->get('/subscribe')
        ->assertOk()
        ->assertSee('Pasa a anual y ahorra un 20 %')
        ->assertSee('Tu plan actual')
        ->assertSee('Gestionar suscripción')
        ->assertSee('Pasar a anual')
        ->assertDontSee('Suscribirme al mes');
});

it('shows the scheduled change and hides the upgrade cards', function () {
    $user = pricingPageUser();
    $user->update(['language' => 'es']);
    pricingPageSubscription($user, [
        'stripe_schedule_id' => 'sub_sched_test',
        'scheduled_tier' => '100gb',
        'scheduled_price_id' => 'price_100_annual',
        'scheduled_change_at' => now()->addDays(10),
    ]);

    actingAs($user->fresh())
        ->get('/subscribe')
        ->assertOk()
        ->assertSee('Cambio a anual programado')
        ->assertSee('Cancelar cambio programado')
        ->assertDontSee('Pasar a anual');
});

it('shows the current plan and billing link to an annual team', function () {
    $user = pricingPageUser();
    $user->update(['language' => 'es']);
    pricingPageSubscription($user, ['billing_interval' => 'year']);

    actingAs($user->fresh())
        ->get('/subscribe')
        ->assertOk()
        ->assertSee('Tu plan actual')
        ->assertSee('Anual')
        ->assertSee('Gestionar suscripción')
        ->assertDontSee('Pasar a anual');
});

it('shows the current plan to a lifetime team', function () {
    $user = pricingPageUser(['lifetime_at' => now()]);
    $user->update(['language' => 'es']);

    actingAs($user->fresh())
        ->get('/subscribe')
        ->assertOk()
        ->assertSee('Tu plan actual')
        ->assertSee('Pago único')
        ->assertDontSee('Pasar a anual');
});

it('shows regular prices when the campaign is over', function () {
    $this->travelTo('2026-12-02 00:00:00');

    config([
        'promotions.black_friday.force' => false,
    ]);

    $user = pricingPageUser();
    $user->update(['language' => 'es']);

    actingAs($user->fresh())
        ->get('/subscribe')
        ->assertOk()
        ->assertDontSee('Black Friday 2026')
        ->assertSee('7,00', false);

    actingAs($user->fresh())
        ->get('/subscribe?interval=annual')
        ->assertOk()
        ->assertDontSee('Black Friday 2026')
        ->assertSee('60,00', false);
});
