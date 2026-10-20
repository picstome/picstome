<?php

use App\Models\User;
use App\Services\CalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calculates the default calculator scenario', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $state = CalculatorService::forTeam($user->currentTeam);
    $summary = CalculatorService::summary($state);

    expect($summary['fixed_total'])->toBe(425.0)
        ->and($summary['fixed_per_session'])->toBe(42.5)
        ->and($summary['session_total'])->toBe(85.0)
        ->and($summary['equipment_total'])->toBe(2000.0)
        ->and($summary['monthly_depreciation'])->toBe(25.0)
        ->and($summary['depreciation_per_session'])->toBe(2.5)
        ->and($summary['session_hours'])->toBe(6.0)
        ->and($summary['session_time_cost'])->toBe(300.0)
        ->and($summary['monthly_hours'])->toBe(23.0)
        ->and($summary['monthly_time_per_session'])->toBe(115.0)
        ->and($summary['total_time_per_session'])->toBe(8.3)
        ->and($summary['base_cost'])->toBe(130.0)
        ->and($summary['time_cost'])->toBe(415.0)
        ->and($summary['base_price'])->toBe(545.0)
        ->and($summary['vat_amount'])->toBe(114.45)
        ->and($summary['invoice_total'])->toBe(659.45)
        ->and($summary['income_tax_withheld'])->toBe(81.75)
        ->and($summary['client_pays'])->toBe(577.7)
        ->and($summary['net_received'])->toBe(463.25)
        ->and($summary['effective_gross_hourly'])->toBe(65.66)
        ->and($summary['effective_net_hourly'])->toBe(55.81);
});

it('depreciates equipment after its resale value', function () {
    $state = CalculatorService::normalize([
        'settings' => [
            'sessions_per_month' => 10,
        ],
        'equipment' => [
            [
                'name' => 'Camera',
                'price' => 1000,
                'resale_value' => 200,
                'useful_life_years' => 5,
            ],
        ],
    ]);

    $summary = CalculatorService::summary($state);

    expect($summary['monthly_depreciation'])->toBe(13.33)
        ->and($summary['depreciation_per_session'])->toBe(1.33)
        ->and($summary['equipment_total'])->toBe(1000.0);
});

it('handles zero hours and zero taxes', function () {
    $state = CalculatorService::normalize([
        'settings' => [
            'hourly_rate' => 50,
            'sessions_per_month' => 10,
            'vat_percent' => 0,
            'income_tax_percent' => 0,
        ],
        'session_tasks' => [],
        'monthly_tasks' => [],
    ]);

    $summary = CalculatorService::summary($state);

    expect($summary['total_time_per_session'])->toBe(0.0)
        ->and($summary['effective_gross_hourly'])->toBe(0.0)
        ->and($summary['effective_net_hourly'])->toBe(0.0)
        ->and($summary['vat_amount'])->toBe(0.0)
        ->and($summary['income_tax_withheld'])->toBe(0.0)
        ->and($summary['client_pays'])->toBe($summary['base_price'])
        ->and($summary['net_received'])->toBe($summary['base_price']);
});

it('normalizes invalid values', function () {
    $state = CalculatorService::normalize([
        'settings' => [
            'hourly_rate' => -10,
            'sessions_per_month' => 0,
            'vat_percent' => 120,
            'income_tax_percent' => -5,
        ],
        'fixed_expenses' => [
            ['name' => 'Negative', 'amount' => -20],
        ],
        'equipment' => [
            ['name' => 'Resale above price', 'price' => 100, 'resale_value' => 200, 'useful_life_years' => 0],
        ],
        'session_tasks' => [
            ['name' => 'Negative hours', 'hours' => -3],
        ],
    ]);

    expect($state['settings']['hourly_rate'])->toBe(0.0)
        ->and($state['settings']['sessions_per_month'])->toBe(1)
        ->and($state['settings']['vat_percent'])->toBe(100.0)
        ->and($state['settings']['income_tax_percent'])->toBe(0.0)
        ->and($state['fixed_expenses'][0]['amount'])->toBe(0.0)
        ->and($state['equipment'][0]['resale_value'])->toBe(100.0)
        ->and($state['equipment'][0]['useful_life_years'])->toBe(1)
        ->and($state['session_tasks'][0]['hours'])->toBe(0.0);
});

it('keeps the guide dismissal when resetting', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $state = CalculatorService::forTeam($team);
    $state['settings']['guide_dismissed_at'] = '2026-10-20T12:00:00+00:00';
    CalculatorService::save($team, $state);

    $state['fixed_expenses'][] = [
        'id' => 'custom',
        'name' => 'Extra',
        'amount' => 100,
    ];
    CalculatorService::save($team, $state);

    $reset = CalculatorService::reset($team);

    expect($reset['settings']['guide_dismissed_at'])->toBe('2026-10-20T12:00:00+00:00')
        ->and($reset['fixed_expenses'])->toHaveCount(4)
        ->and($reset['fixed_expenses'][0]['name'])->toBe('Self-Employment Quota');
});

it('translates default item names', function () {
    app()->setLocale('es');

    $state = CalculatorService::defaults();

    expect($state['fixed_expenses'][0]['name'])->toBe('Cuota de Autónomos')
        ->and($state['session_tasks'][0]['name'])->toBe('Tiempo de Disparo');
});
