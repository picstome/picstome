<?php

use App\Models\ContractTemplate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('users can view their team template', function () {
    $template = ContractTemplate::factory()->for($this->team)->create();

    $response = actingAs($this->user)->get('/contract-templates/1');

    $response->assertStatus(200);

    $component = Livewire::actingAs($this->user)->test('pages::contract-templates.show', ['contractTemplate' => $template]);

    expect($component->contractTemplate->is($template))->toBeTrue();
});

test('guests cannot view any templates', function () {
    ContractTemplate::factory()->create();

    $response = get('/contract-templates/1');

    $response->assertRedirect('/login');
});

test('users cannot view the team templates of others', function () {
    $contract = ContractTemplate::factory()->for(Team::factory())->create();

    $response = actingAs($this->user)->get('/contract-templates/1');

    $response->assertStatus(403);
});

test('user can edit a template', function () {
    $template = ContractTemplate::factory()->for($this->team)->create();

    $component = Livewire::actingAs($this->user)->test('pages::contract-templates.show', ['contractTemplate' => $template])
        ->set('form.title', 'New contract title')
        ->set('form.body', '<h3>New body in HTML</h3>')
        ->call('save');

    tap($template->fresh(), function (ContractTemplate $template) {
        expect($template->title)->toBe('New contract title');
        expect($template->markdown_body)->toBe('### New body in HTML');
        expect((string) $template->formatted_markdown_body)->toContain('<h3>New body in HTML</h3>');
    });
});

test('user can delete a template', function () {
    $template = ContractTemplate::factory()->for($this->team)->create();

    $component = Livewire::actingAs($this->user)->test('pages::contract-templates.show', ['contractTemplate' => $template])
        ->call('delete');

    $component->assertRedirect('/contract-templates');
    expect(ContractTemplate::count())->toBe(0);
});
