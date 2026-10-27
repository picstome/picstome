<?php

use App\Models\Gallery;
use App\Models\Photo;
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
        ->assertSee('Average photos per user')
        ->assertSee('Stripe payments')
        ->assertSee('Stripe accounts')
        ->assertSee('Content')
        ->assertSee('Users and plans')
        ->assertSee('Library')
        ->assertSee('Monetization')
        ->assertSee('Click a metric to see its 12-month chart')
        ->assertSeeHtml('data-test="invoices-card"')
        ->assertSeeHtml('data-test="invoices-total-card"')
        ->assertSeeHtml('data-test="stripe-payments-card"')
        ->assertSeeHtml('data-test="stripe-payments-total-card"')
        ->assertSeeHtml('data-test="stripe-accounts-card"');
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

it('shows the chart tooltip over the bottom of the latest bar', function () {
    $admin = User::factory()->withPersonalTeam()->create([
        'email' => 'admin@example.com',
    ]);

    $gallery = Gallery::factory()->for($admin->personalTeam())->create();

    Photo::factory()->for($gallery)->create(['created_at' => now()]);

    $this->actingAs($admin);

    $page = visit('/admin/stats');

    $page->click('@photos-card')->assertVisible('@usage-chart-content');

    usleep(1_000_000);

    $tooltip = $page->page()->evaluate(<<<'JS'
        async () => {
            const chart = document.querySelector('[data-test="usage-chart-content"] svg[dusk="tallstackui_chart"]');
            const rect = chart.getBoundingClientRect();

            chart.dispatchEvent(new PointerEvent('pointermove', {
                bubbles: true,
                clientX: rect.right - 20,
                clientY: rect.bottom - 20,
            }));

            await new Promise((resolve) => setTimeout(resolve, 50));

            const tooltip = document.querySelector('[dusk="tallstackui_chart_tooltip"]');
            const style = tooltip ? getComputedStyle(tooltip) : null;

            return {
                active: !! tooltip && style.display !== 'none' && style.visibility !== 'hidden',
                text: tooltip?.innerText.trim() ?? '',
            };
        }
    JS);

    expect($tooltip['active'])->toBeTrue()
        ->and($tooltip['text'])->toContain('Photos');
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
            now()->subMonths(2)->toDateString(),
            now()->subMonth()->toDateString(),
        ])
        ->set('preset', 'custom')
        ->call('refreshStats')
        ->assertHasNoErrors();
});
