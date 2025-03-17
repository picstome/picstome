<?php

use App\Models\Contract;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('users can view their team contracts', function () {
    $contractA = Contract::factory()->for($this->team)->create();
    $contractB = Contract::factory()->for(Team::factory())->create();
    $contractC = Contract::factory()->for($this->team)->create();

    $response = actingAs($this->user)->get('/contracts');
    $component = Volt::test('pages.contracts');

    $response->assertStatus(200);
    $component->assertViewHas('contracts');
    expect($component->viewData('contracts')->contains($contractA))->toBeTrue();
    expect($component->viewData('contracts')->contains($contractB))->toBeFalse();
    expect($component->viewData('contracts')->contains($contractC))->toBeTrue();
});

test('can add new contract', function () {
    $component = Volt::actingAs($this->user)->test('pages.contracts')
        ->set('form.title', 'A contract title')
        ->set('form.description', 'A contract description')
        ->set('form.location', 'A location')
        ->set('form.shootingDate', Carbon::parse('12-12-2025'))
        ->set('form.body', '<h3>Body in HTML</h3>')
        ->set('form.signature_quantity', 3)
        ->call('save');

    $component->assertRedirect('/contracts/1');
    expect(Contract::count())->toBe(1);
    tap(Contract::first(), function (Contract $contract) {
        expect($contract->title)->toBe('A contract title');
        expect($contract->description)->toBe('A contract description');
        expect($contract->location)->toBe('A location');
        expect((string) $contract->shooting_date)->toBe('2025-12-12 00:00:00');
        expect($contract->markdown_body)->toBe('### Body in HTML');
        expect($contract->signaturesRemaining())->toBe(3);
        $contract->signatures()->each(function ($contract) {
            expect($contract->isSigned())->toBeFalse();
        });
    });
});

test('guests cannot create contracts', function () {
    $component = Volt::test('pages.contracts')->call('save');

    $component->assertStatus(403);
});
