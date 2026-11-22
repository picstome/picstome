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

test('any user can view a Picstome template', function () {
    $template = ContractTemplate::factory()->create([
        'team_id' => null,
        'locale' => 'en',
        'title' => 'Picstome release',
    ]);

    actingAs($this->user)
        ->get('/contract-templates/'.$template->id)
        ->assertOk()
        ->assertSee('Picstome release')
        ->assertSee(__('Picstome contract template'));
});

test('regular users cannot update or delete a Picstome template', function () {
    $template = ContractTemplate::factory()->create([
        'team_id' => null,
        'locale' => 'en',
        'title' => 'Picstome release',
    ]);

    $component = Livewire::actingAs($this->user)->test('pages::contract-templates.show', ['contractTemplate' => $template])
        ->submit([
            'form.title' => 'Changed by a user',
        ], 'save');

    $component->assertStatus(403);

    Livewire::actingAs($this->user)->test('pages::contract-templates.show', ['contractTemplate' => $template])
        ->call('delete')
        ->assertStatus(403);

    expect($template->fresh()->title)->toBe('Picstome release');
});

test('admins can update a Picstome template', function () {
    config(['picstome.admin_emails' => [$this->user->email]]);

    $template = ContractTemplate::factory()->create([
        'team_id' => null,
        'locale' => 'es',
        'title' => 'Título original',
    ]);

    Livewire::actingAs($this->user)->test('pages::contract-templates.show', ['contractTemplate' => $template])
        ->submit([
            'form.title' => 'Título actualizado',
            'form.body' => '<h3>Nuevo cuerpo</h3>',
        ], 'save');

    tap($template->fresh(), function (ContractTemplate $template) {
        expect($template->title)->toBe('Título actualizado');
        expect($template->markdown_body)->toBe('### Nuevo cuerpo');
        expect($template->team_id)->toBeNull();
        expect($template->locale)->toBe('es');
    });
});

test('user can edit a template', function () {
    $template = ContractTemplate::factory()->for($this->team)->create();

    $component = Livewire::actingAs($this->user)->test('pages::contract-templates.show', ['contractTemplate' => $template])
        ->submit([
            'form.title' => 'New contract title',
            'form.body' => '<h3>New body in HTML</h3>',
        ], 'save');

    tap($template->fresh(), function (ContractTemplate $template) {
        expect($template->title)->toBe('New contract title');
        expect($template->markdown_body)->toBe('### New body in HTML');
        expect((string) $template->formatted_markdown_body)->toContain('<h3>New body in HTML</h3>');
    });
});

test('template preview strips raw html and unsafe links', function () {
    $template = ContractTemplate::factory()->for($this->team)->create([
        'markdown_body' => '<img src=x onerror="alert(1)"> [click](javascript:alert(1)) **safe**',
    ]);

    $html = (string) $template->formatted_markdown_body;

    expect($html)->not->toContain('<img')
        ->and($html)->not->toContain('onerror')
        ->and($html)->not->toContain('javascript:')
        ->and($html)->toContain('<strong>safe</strong>');
});

test('user can delete a template', function () {
    $template = ContractTemplate::factory()->for($this->team)->create();

    $component = Livewire::actingAs($this->user)->test('pages::contract-templates.show', ['contractTemplate' => $template])
        ->call('delete');

    $component->assertRedirect('/contract-templates');
    expect(ContractTemplate::count())->toBe(0);
});
