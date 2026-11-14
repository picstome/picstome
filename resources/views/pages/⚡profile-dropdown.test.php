<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;

uses(RefreshDatabase::class);

test('account menu links to branding', function () {
    $user = User::factory()->withPersonalTeam()->create();

    actingAs($user);

    Livewire::test('profile-dropdown')
        ->assertSee(route('branding'), false)
        ->assertDontSee('Install the app');
});

test('account menu shows the current plan and links to subscription', function () {
    app()->setLocale('es');

    $user = User::factory()->withPersonalTeam()->create();
    $user->switchTeam($user->personalTeam());

    actingAs($user->fresh());

    Livewire::test('profile-dropdown')
        ->assertSee('Suscripción (Gratis)')
        ->assertSee(route('subscribe'), false);
});

test('account menu links a paid plan to the billing portal', function () {
    app()->setLocale('es');

    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->personalTeam();
    $team->update(['custom_storage_limit' => 107374182400]);
    $user->switchTeam($team);

    DB::table('subscriptions')->insert([
        'team_id' => $team->id,
        'type' => 'default',
        'stripe_id' => 'sub_monthly',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly',
        'quantity' => 1,
        'billing_interval' => 'month',
        'current_period_end' => now()->addMonth(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($user->fresh());

    Livewire::test('profile-dropdown')
        ->assertSee('Suscripción (100 GB · Mensual)')
        ->assertSee(route('billing-portal'), false);
});

test('users can logout', function () {
    $user = User::factory()->withPersonalTeam()->create();

    actingAs($user);

    $component = Livewire::test('profile-dropdown');

    $component->call('logout');

    $component
        ->assertHasNoErrors()
        ->assertRedirect('/');

    assertGuest();
});
