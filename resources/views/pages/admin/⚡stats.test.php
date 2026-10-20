<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config(['picstome.admin_emails' => ['admin@example.com']]);
});

it('allows an admin to view usage stats', function () {
    $admin = User::factory()->withPersonalTeam()->create([
        'email' => 'admin@example.com',
    ]);

    actingAs($admin)
        ->get('/admin/stats')
        ->assertOk()
        ->assertSee('Usage stats')
        ->assertSee('New in period')
        ->assertSee('Current totals')
        ->assertSee('Average photos per user');
});

it('forbids access to usage stats for non-admins', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'email' => 'user@example.com',
    ]);

    actingAs($user)->get('/admin/stats')->assertForbidden();
});

it('renders the monthly charts for usage metrics', function () {
    $admin = User::factory()->withPersonalTeam()->create([
        'email' => 'admin@example.com',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.stats')
        ->assertSee('Last 12 months, including the current month.')
        ->assertSee('Photos')
        ->assertSee('Lifetime');
});

it('opens the monthly chart modal when a metric card is clicked', function () {
    $admin = User::factory()->withPersonalTeam()->create([
        'email' => 'admin@example.com',
    ]);

    $this->actingAs($admin);

    visit('/admin/stats')
        ->click('@photos-card')
        ->assertVisible('@usage-chart-content')
        ->assertSee('Last 12 months, including the current month.')
        ->assertNoJavascriptErrors();
});

it('switches periods and refreshes the cached stats', function () {
    $admin = User::factory()->withPersonalTeam()->create([
        'email' => 'admin@example.com',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.stats')
        ->assertSet('preset', 'current_month')
        ->set('preset', 'last_30_days')
        ->assertSet('preset', 'last_30_days')
        ->set('customRange', [
            'start' => now()->subMonths(2)->toDateString(),
            'end' => now()->subMonth()->toDateString(),
        ])
        ->set('preset', 'custom')
        ->call('refreshStats')
        ->assertHasNoErrors();
});
