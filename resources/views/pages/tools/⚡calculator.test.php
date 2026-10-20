<?php

use App\Models\User;
use App\Services\CalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()->setLocale('en');
});

it('creates the default calculator state on first visit', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    expect($team->calculatorState)->toBeNull();

    $component = Livewire::actingAs($user)->test('pages::tools.calculator');

    $component->assertHasNoErrors()
        ->assertSee('Session price calculator')
        ->assertSee('Apply IRPF withholding')
        ->assertSee('Base price');

    $state = $team->fresh()->calculatorState->state;

    expect($state['settings']['income_tax_withholding'])->toBeFalse()
        ->and($state['fixed_expenses'])->toHaveCount(4)
        ->and($state['session_expenses'])->toHaveCount(3)
        ->and($state['equipment'])->toHaveCount(2)
        ->and($state['session_tasks'])->toHaveCount(3)
        ->and($state['monthly_tasks'])->toHaveCount(3);
});

it('persists quick setting changes', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    Livewire::actingAs($user)->test('pages::tools.calculator')
        ->set('settings.hourly_rate', 80)
        ->set('settings.sessions_per_month', 8)
        ->set('settings.vat_percent', 10)
        ->set('settings.income_tax_percent', 5)
        ->set('settings.income_tax_withholding', true);

    $settings = $team->fresh()->calculatorState->state['settings'];

    expect($settings['hourly_rate'])->toEqual(80)
        ->and($settings['sessions_per_month'])->toBe(8)
        ->and($settings['vat_percent'])->toEqual(10)
        ->and($settings['income_tax_percent'])->toEqual(5)
        ->and($settings['income_tax_withholding'])->toBeTrue();
});

it('adds and deletes a fixed expense', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $component = Livewire::actingAs($user)->test('pages::tools.calculator')
        ->set('newFixedName', 'New expense')
        ->set('newFixedAmount', '99.50')
        ->call('addFixedExpense')
        ->assertHasNoErrors();

    expect($component->get('fixed_expenses'))->toHaveCount(5);

    $state = $team->fresh()->calculatorState->state;
    expect($state['fixed_expenses'][4]['name'])->toBe('New expense')
        ->and($state['fixed_expenses'][4]['amount'])->toBe(99.5);

    $id = $component->get('fixed_expenses')[4]['id'];

    $component->call('deleteItem', 'fixed', $id);

    expect($component->get('fixed_expenses'))->toHaveCount(4);
});

it('validates new fixed expenses', function () {
    $user = User::factory()->withPersonalTeam()->create();

    Livewire::actingAs($user)->test('pages::tools.calculator')
        ->set('newFixedName', '')
        ->set('newFixedAmount', '')
        ->call('addFixedExpense')
        ->assertHasErrors(['newFixedName', 'newFixedAmount']);
});

it('adds equipment with resale value and useful life', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    Livewire::actingAs($user)->test('pages::tools.calculator')
        ->set('newEquipmentName', 'Backup camera')
        ->set('newEquipmentPrice', '1000')
        ->set('newEquipmentResale', '200')
        ->set('newEquipmentLife', '5')
        ->call('addEquipment')
        ->assertHasNoErrors();

    $equipment = collect($team->fresh()->calculatorState->state['equipment'])->last();

    expect($equipment['name'])->toBe('Backup camera')
        ->and($equipment['price'])->toEqual(1000)
        ->and($equipment['resale_value'])->toEqual(200)
        ->and($equipment['useful_life_years'])->toBe(5);
});

it('includes monthly tasks in the calculated summary', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    Livewire::actingAs($user)->test('pages::tools.calculator')
        ->set('newMonthlyTaskName', 'Networking')
        ->set('newMonthlyTaskHours', '10')
        ->call('addMonthlyTask')
        ->assertHasNoErrors();

    $summary = CalculatorService::summary($team->fresh()->calculatorState->state);

    expect($summary['monthly_hours'])->toBe(33.0)
        ->and($summary['monthly_time_cost'])->toBe(1650.0)
        ->and($summary['monthly_time_per_session'])->toBe(165.0)
        ->and($summary['time_cost'])->toBe(465.0);
});

it('resets the calculator to defaults', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    Livewire::actingAs($user)->test('pages::tools.calculator')
        ->set('newFixedName', 'Temporary')
        ->set('newFixedAmount', '10')
        ->call('addFixedExpense')
        ->call('resetCalculator')
        ->assertHasNoErrors();

    $state = $team->fresh()->calculatorState->state;
    $summary = CalculatorService::summary($state);

    expect($state['settings']['income_tax_withholding'])->toBeFalse()
        ->and($state['fixed_expenses'])->toHaveCount(4)
        ->and($summary['base_price'])->toBe(545.0);
});

it('keeps calculator data isolated per team', function () {
    $firstUser = User::factory()->withPersonalTeam()->create();
    $secondUser = User::factory()->withPersonalTeam()->create();

    Livewire::actingAs($firstUser)->test('pages::tools.calculator')
        ->set('newFixedName', 'First team only')
        ->set('newFixedAmount', '10')
        ->call('addFixedExpense');

    Livewire::actingAs($secondUser)->test('pages::tools.calculator');

    $firstNames = collect($firstUser->fresh()->currentTeam->calculatorState->state['fixed_expenses'])->pluck('name');
    $secondNames = collect($secondUser->fresh()->currentTeam->calculatorState->state['fixed_expenses'])->pluck('name');

    expect($firstNames)->toContain('First team only')
        ->and($secondNames)->not->toContain('First team only');
});

it('dismisses the guide', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    Livewire::actingAs($user)->test('pages::tools.calculator')
        ->assertSee('How this calculator works')
        ->call('dismissGuide');

    expect($team->fresh()->calculatorState->state['settings']['guide_dismissed_at'])->not->toBeNull();
});

it('formats amounts with the team currency', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $user->currentTeam->update(['stripe_currency' => 'usd']);

    Livewire::actingAs($user)->test('pages::tools.calculator')
        ->assertSee('USD');
});

it('validates equipment resale value against the price', function () {
    $user = User::factory()->withPersonalTeam()->create();

    Livewire::actingAs($user)->test('pages::tools.calculator')
        ->set('newEquipmentName', 'Invalid equipment')
        ->set('newEquipmentPrice', '100')
        ->set('newEquipmentResale', '200')
        ->set('newEquipmentLife', '5')
        ->call('addEquipment')
        ->assertHasErrors(['newEquipmentResale']);
});

it('adds session expenses and session time', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    Livewire::actingAs($user)->test('pages::tools.calculator')
        ->set('newSessionName', 'Makeup')
        ->set('newSessionAmount', '40')
        ->call('addSessionExpense')
        ->assertHasNoErrors()
        ->set('newSessionTaskName', 'Retouching')
        ->set('newSessionTaskHours', '2')
        ->call('addSessionTask')
        ->assertHasNoErrors();

    $state = $team->fresh()->calculatorState->state;

    expect(collect($state['session_expenses'])->pluck('name'))->toContain('Makeup')
        ->and(collect($state['session_tasks'])->pluck('name'))->toContain('Retouching');
});
