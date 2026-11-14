<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function accountSummaryUser(array $teamAttributes = []): User
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

it('shows the plan, storage and billing actions', function () {
    app()->setLocale('es');

    $user = accountSummaryUser();
    $renewsAt = now()->addMonth();

    DB::table('subscriptions')->insert([
        'team_id' => $user->current_team_id,
        'type' => 'default',
        'stripe_id' => 'sub_monthly',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly',
        'quantity' => 1,
        'billing_interval' => 'month',
        'current_period_end' => $renewsAt,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($user->fresh())
        ->test('storage-usage-indicator')
        ->assertSee('Plan 100 GB · Mensual')
        ->assertSee('Renueva el '.$renewsAt->format('d/m/Y'))
        ->assertSee('Suscripción')
        ->assertSee(route('subscribe'), false);
});

it('shows the free plan to a team without a subscription', function () {
    app()->setLocale('es');

    $user = accountSummaryUser(['custom_storage_limit' => 1073741824]);

    Livewire::actingAs($user->fresh())
        ->test('storage-usage-indicator')
        ->assertSee('Plan Gratis')
        ->assertDontSee('Sin suscripción')
        ->assertSee('Suscripción');
});
