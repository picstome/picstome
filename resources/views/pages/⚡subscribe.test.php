<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use App\Services\StorageUsageService;
use App\Services\SubscriptionCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

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
        ->assertSee('Volver al panel')
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

it('shows a toast instead of a server error when checkout fails', function () {
    app()->setLocale('es');

    $user = pricingPageUser();

    $this->mock(SubscriptionCheckoutService::class, function ($mock) {
        $mock->shouldReceive('checkout')->andThrow(new RuntimeException('Stripe unavailable'));
    });

    Livewire::actingAs($user->fresh())
        ->test('pages::subscribe')
        ->call('checkout', '100gb', 'monthly')
        ->assertSet('statusType', 'error')
        ->assertSet('statusMessage', 'No hemos podido iniciar el pago. Inténtalo de nuevo.');
});

it('renders the plan buttons without leaking analytics attributes', function () {
    get('/subscribe')
        ->assertOk()
        ->assertDontSee('plans.currency')
        ->assertDontSee('picstomeTrack');
});

it('tracks the start of a checkout', function () {
    Livewire::test('pages::subscribe')
        ->call('checkout', '100gb', 'monthly')
        ->assertDispatched('analytics:event');
});

it('disables a smaller plan that does not fit the used storage', function () {
    app()->setLocale('es');

    $user = pricingPageUser(['custom_storage_limit' => 268435456000]);
    $user->update(['language' => 'es']);
    pricingPageSubscription($user, ['billing_interval' => 'month']);

    $gallery = Gallery::factory()->for($user->currentTeam)->create();
    Photo::factory()->for($gallery)->create(['stored_size' => 150 * 1024 ** 3]);
    StorageUsageService::flushRequestMemo();

    actingAs($user->fresh())
        ->get('/subscribe')
        ->assertOk()
        ->assertSee('Espacio insuficiente')
        ->assertSee('150 GB')
        ->assertDontSee('data-test="upgrade-100gb"', false)
        ->assertSee('data-test="upgrade-250gb"', false);
});

it('blocks checkout when the used storage does not fit the plan', function () {
    app()->setLocale('es');

    $user = pricingPageUser(['custom_storage_limit' => 268435456000]);
    $user->update(['language' => 'es']);
    pricingPageSubscription($user, ['billing_interval' => 'month']);

    $gallery = Gallery::factory()->for($user->currentTeam)->create();
    Photo::factory()->for($gallery)->create(['stored_size' => 150 * 1024 ** 3]);
    StorageUsageService::flushRequestMemo();

    Livewire::actingAs($user->fresh())
        ->test('pages::subscribe')
        ->call('checkout', '100gb', 'monthly')
        ->assertSet('statusType', 'warning')
        ->assertSee('150 GB');
});

it('keeps the query parameters when a guest goes to login from the pricing page', function () {
    $original = '/subscribe?plan=pro&storage=250gb&billing=monthly&ps_aid=eyJzIjoidGVzdCJ9&ps_from=bf2026';

    $html = get($original)->assertOk()->getContent();

    preg_match('/href="([^"]*\/login\?redirect=[^"]*)"/', $html, $matches);

    expect($matches[1] ?? null)->not->toBeNull();

    parse_str((string) parse_url(html_entity_decode($matches[1]), PHP_URL_QUERY), $query);

    expect($query['redirect'] ?? null)->toBe($original.'&interval=monthly');
});

it('keeps the query parameters when a guest starts a plan', function () {
    $html = get('/subscribe?storage=250gb&billing=monthly&ps_from=bf2026')->assertOk()->getContent();

    preg_match_all('/href="([^"]*\/register\?redirect=[^"]*)"/', $html, $matches);

    $redirects = collect($matches[1])
        ->map(function (string $href): string {
            parse_str((string) parse_url(html_entity_decode($href), PHP_URL_QUERY), $query);

            return (string) ($query['redirect'] ?? '');
        });

    expect($redirects->first(fn (string $url): bool => str_contains($url, 'plan=100gb')))
        ->toContain('storage=250gb')
        ->toContain('billing=monthly')
        ->toContain('ps_from=bf2026')
        ->toContain('interval=monthly');

    expect($redirects->first(fn (string $url): bool => ! str_contains($url, 'plan=')))
        ->toContain('storage=250gb')
        ->toContain('billing=monthly')
        ->toContain('ps_from=bf2026')
        ->toContain('interval=monthly');
});

it('keeps the query parameters when a guest starts a checkout', function () {
    Livewire::withQueryParams([
        'storage' => '250gb',
        'billing' => 'monthly',
        'ps_from' => 'bf2026',
    ])
        ->test('pages::subscribe')
        ->call('checkout', '100gb', 'monthly')
        ->assertRedirect(route('register', [
            'redirect' => '/subscribe?storage=250gb&billing=monthly&ps_from=bf2026&plan=100gb&interval=monthly',
        ], absolute: false));
});

it('shows a single manage subscription button and a panel link', function () {
    $user = pricingPageUser();
    $user->update(['language' => 'es']);
    pricingPageSubscription($user);

    $response = actingAs($user->fresh())->get('/subscribe');

    $response->assertOk()
        ->assertSee('Volver al panel')
        ->assertSee('Gestionar suscripción');

    expect(substr_count($response->getContent(), 'Gestionar suscripción'))->toBe(1);
});
