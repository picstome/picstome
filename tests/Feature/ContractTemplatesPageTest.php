<?php

use App\Models\ContractTemplate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('users can view their team contract templates', function () {
    $templateA = ContractTemplate::factory()->for($this->team)->create();
    $templateB = ContractTemplate::factory()->for(Team::factory())->create();
    $templateC = ContractTemplate::factory()->for($this->team)->create();

    $response = actingAs($this->user)->get('/contract-templates');
    $component = Volt::test('pages.contract-templates');

    $response->assertStatus(200);
    expect($component->templates->count())->toBe(2);
    expect($component->templates->contains($templateA))->toBeTrue();
    expect($component->templates->contains($templateB))->toBeFalse();
    expect($component->templates->contains($templateC))->toBeTrue();
});

test('can add new contract', function () {
    $component = Volt::actingAs($this->user)->test('pages.contract-templates')
        ->set('form.title', 'A contract title')
        ->set('form.body', '<h3>Body in HTML</h3>')
        ->call('save');

    $component->assertRedirect('/contract-templates/1');
    expect(ContractTemplate::count())->toBe(1);
    tap(ContractTemplate::first(), function (ContractTemplate $template) {
        expect($template->title)->toBe('A contract title');
        expect($template->markdown_body)->toBe('### Body in HTML');
        expect((string) $template->formatted_markdown_body)->toContain('<h3>Body in HTML</h3>');
    });
});

test('guests cannot create templates', function () {
    $component = Volt::test('pages.contract-templates')->call('save');

    $component->assertStatus(403);
});
