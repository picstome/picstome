<?php

use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('appearance page is displayed', function () {
    actingAs($user = User::factory()->withPersonalTeam()->create());

    get('/settings/appearance')->assertOk();
});

test('appearance information can be updated', function () {
    $user = User::factory()->withPersonalTeam()->create();
    expect($user->language)->toBeNull();

    $response = Volt::actingAs($user)->test('pages.settings.appearance')
        ->set('form.language', 'es')
        ->call('save');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->language)->toEqual('es');
});

test('preferred language is being applied', function () {
    $user = User::factory()->withPersonalTeam()->create(['language' => 'es']);
    expect(app()->getLocale())->toBe('en');

    actingAs($user)->get('/settings/appearance');

    expect(app()->getLocale())->toBe('es');
});
