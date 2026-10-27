<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('allows users to see the styling branding page', function () {
    $response = actingAs($this->user)->get('/branding/styling');

    $response->assertStatus(200)
        ->assertSee('Picstome (default)')
        ->assertSee(route('handle.show', ['handle' => $this->team->handle]), false);
});

it('can change brand color', function () {
    expect($this->team->brand_color)->not()->toBe('red');

    Livewire::actingAs($this->user)->test('pages::branding.styling')
        ->set('form.color', 'red')
        ->call('save');

    expect($this->team->fresh()->brand_color)->toBe('red');
});

it('can change brand font', function () {
    expect($this->team->brand_font)->toBeNull();

    Livewire::actingAs($this->user)->test('pages::branding.styling')
        ->set('form.font', 'Montserrat')
        ->call('save');

    expect($this->team->fresh()->brand_font)->toBe('Montserrat');
});

it('updates the preview button when the accent color changes', function () {
    $component = Livewire::actingAs($this->user)->test('pages::branding.styling');

    $component->set('form.color', 'violet');

    expect($component->html())->toContain('bg-violet-500');
});

it('updates the preview button font when the font changes', function () {
    $component = Livewire::actingAs($this->user)->test('pages::branding.styling');

    $component->set('form.font', 'Montserrat');

    $html = $component->html();

    expect($html)
        ->toContain('fonts.googleapis.com/css2?family=Montserrat')
        ->toContain('font-family: &#039;Montserrat&#039;, sans-serif');
});
