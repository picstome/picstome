<?php

use App\Models\ContractTemplate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

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
    $component = Livewire::test('pages::contract-templates');

    $response->assertStatus(200);
    expect($component->templates->count())->toBe(2);
    expect($component->templates->contains($templateA))->toBeTrue();
    expect($component->templates->contains($templateB))->toBeFalse();
    expect($component->templates->contains($templateC))->toBeTrue();
});

test('regular users do not see Picstome templates in their template list', function () {
    $template = ContractTemplate::factory()->create([
        'team_id' => null,
        'locale' => 'en',
        'title' => 'Picstome release',
    ]);

    $component = Livewire::actingAs($this->user)->test('pages::contract-templates');

    expect($component->templates->contains($template))->toBeFalse();
    expect($component->availableTemplates->contains($template))->toBeTrue();
});

test('admins see Picstome templates in their template list', function () {
    config(['picstome.admin_emails' => [$this->user->email]]);

    $template = ContractTemplate::factory()->create([
        'team_id' => null,
        'locale' => 'en',
        'title' => 'Picstome release',
    ]);

    $component = Livewire::actingAs($this->user)->test('pages::contract-templates');

    expect($component->templates->contains($template))->toBeTrue();
});

test('admins can publish a template in a chosen language', function () {
    config(['picstome.admin_emails' => [$this->user->email]]);

    $component = Livewire::actingAs($this->user)->test('pages::contract-templates')
        ->set('form.title', 'Licencia de marca')
        ->set('form.body', '<h3>Cuerpo en HTML</h3>')
        ->set('form.isPublic', true)
        ->set('form.locale', 'es')
        ->call('save');

    $component->assertRedirect('/contract-templates/1');

    tap(ContractTemplate::first(), function (ContractTemplate $template) {
        expect($template->team_id)->toBeNull();
        expect($template->locale)->toBe('es');
        expect($template->title)->toBe('Licencia de marca');
        expect($template->markdown_body)->toBe('### Cuerpo en HTML');
    });
});

test('regular users cannot publish a template', function () {
    $component = Livewire::actingAs($this->user)->test('pages::contract-templates')
        ->set('form.title', 'Sneaky public template')
        ->set('form.body', '<h3>Body</h3>')
        ->set('form.isPublic', true)
        ->call('save');

    $component->assertStatus(403);
    expect(ContractTemplate::count())->toBe(0);
});

test('users can start a new template from a Picstome template', function () {
    $template = ContractTemplate::factory()->create([
        'team_id' => null,
        'locale' => 'en',
        'markdown_body' => '### Picstome body',
    ]);

    $component = Livewire::actingAs($this->user)->test('pages::contract-templates')
        ->call('useTemplate', $template->id);

    expect($component->form->body)->toContain('<h3>Picstome body</h3>');
});

test('can add new contract', function () {
    $component = Livewire::actingAs($this->user)->test('pages::contract-templates')
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
    $component = Livewire::test('pages::contract-templates')->call('save');

    $component->assertStatus(403);
});
