<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()->setLocale('es');

    config([
        'promotions.black_friday.enabled' => true,
        'promotions.black_friday.force' => true,
        'promotions.black_friday.starts_at' => '2026-11-16 00:00:00',
        'promotions.black_friday.ends_at' => '2026-12-01 00:00:00',
        'promotions.black_friday.timezone' => 'Europe/Madrid',
    ]);
});

function bannerUser(array $teamAttributes = []): User
{
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->personalTeam();

    $team->update($teamAttributes);
    $user->switchTeam($team);

    return $user->fresh();
}

it('shows the free offer banner to a non-paying team', function () {
    $user = bannerUser();

    Livewire::actingAs($user)
        ->test('black-friday-banner', ['forceVisible' => true])
        ->assertSee('Black Friday: tu primer mes desde 1 €')
        ->assertSee('Ver ofertas');
});

it('shows the annual upgrade banner to a monthly team', function () {
    $user = bannerUser();
    DB::table('subscriptions')->insert([
        'team_id' => $user->current_team_id,
        'type' => 'default',
        'stripe_id' => 'sub_monthly',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly',
        'quantity' => 1,
        'billing_interval' => 'month',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($user->fresh())
        ->test('black-friday-banner', ['forceVisible' => true])
        ->assertSee('Black Friday: pasa a anual y ahorra un 20 %')
        ->assertSee('Ver oferta anual');
});

it('does not show the banner to an annual team', function () {
    $user = bannerUser();
    DB::table('subscriptions')->insert([
        'team_id' => $user->current_team_id,
        'type' => 'default',
        'stripe_id' => 'sub_annual',
        'stripe_status' => 'active',
        'stripe_price' => 'price_annual',
        'quantity' => 1,
        'billing_interval' => 'year',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($user->fresh())
        ->test('black-friday-banner', ['forceVisible' => true])
        ->assertDontSee('Black Friday:');
});

it('does not show the banner to a lifetime team', function () {
    $user = bannerUser(['lifetime_at' => now()]);

    Livewire::actingAs($user)
        ->test('black-friday-banner', ['forceVisible' => true])
        ->assertDontSee('Black Friday:');
});

it('dismisses the banner for the session', function () {
    $user = bannerUser();

    Livewire::actingAs($user)
        ->test('black-friday-banner', ['forceVisible' => true])
        ->assertSee('Black Friday: tu primer mes desde 1 €')
        ->call('dismiss')
        ->assertDontSee('Black Friday: tu primer mes desde 1 €');

    expect(session('black_friday_banner_dismissed'))->toBeTrue();
});
