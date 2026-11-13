<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('allows users to see the watermark branding page', function () {
    $response = actingAs($this->user)->get('/branding/watermark');

    $response->assertStatus(200);
});

it('loads the watermark font options for the preview', function () {
    actingAs($this->user)->get('/branding/watermark')
        ->assertSee('family=Roboto+Flex', false)
        ->assertSee('family=Playfair+Display', false);
});

it('can save a brand watermark', function () {
    Storage::fake('s3');
    expect($this->team->brand_watermark_path)->toBeNull();

    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->set('form.watermark', UploadedFile::fake()->image('watermark.png'))
        ->call('save');

    expect($this->team->fresh()->brand_watermark_path)->not->toBeNull();
    expect(Team::first()->brand_watermark_url)->not->toBeNull();
});

it('keeps the save button disabled state in sync with uploads', function () {
    actingAs($this->user)->get('/branding/watermark')
        ->assertSee('x-bind:disabled="uploads > 0', false)
        ->assertDontSee('wire:loading.attr="disabled"', false);
});

it('keeps the existing watermark path available to the save button', function () {
    Storage::fake('s3');

    $this->team->updateBrandWatermark(UploadedFile::fake()->image('watermark.png'));

    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->assertSet('brandWatermarkPath', $this->team->fresh()->brand_watermark_path)
        ->call('deleteWatermark')
        ->assertSet('brandWatermarkPath', null);
});

it('does not save settings when there is no watermark', function () {
    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->set('form.watermarkPosition', 'bottom')
        ->set('form.watermarkTransparency', 30)
        ->call('save')
        ->assertNotDispatched('ts-ui:toast');

    expect($this->team->fresh()->brand_watermark_position)->toBe('top');
    expect($this->team->fresh()->brand_watermark_transparency)->toBeNull();
});

it('can delete the brand watermark', function () {
    Storage::fake('s3');

    $this->team->updateBrandWatermark(UploadedFile::fake()->image('watermark.png'));
    $path = $this->team->fresh()->brand_watermark_path;

    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->call('deleteWatermark')
        ->assertDispatched('ts-ui:toast');

    expect($this->team->fresh()->brand_watermark_path)->toBeNull();
    Storage::disk('s3')->assertMissing($path);
});

it('can save settings when a watermark already exists', function () {
    Storage::fake('s3');

    $this->team->updateBrandWatermark(UploadedFile::fake()->image('watermark.png'));

    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->set('form.watermarkPosition', 'bottom')
        ->set('form.watermarkTransparency', 25)
        ->call('save')
        ->assertDispatched('ts-ui:toast');

    expect($this->team->fresh()->brand_watermark_position)->toBe('bottom');
    expect($this->team->fresh()->brand_watermark_transparency)->toBe(25);
});

it('can change watermark position', function () {
    Storage::fake('s3');

    $this->team->updateBrandWatermark(UploadedFile::fake()->image('watermark.png'));

    expect($this->team->fresh()->brand_watermark_position)->not()->toBe('bottom');

    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->set('form.watermarkPosition', 'bottom')
        ->call('save');

    expect($this->team->fresh()->brand_watermark_position)->toBe('bottom');
});

it('can change watermark transparency', function () {
    Storage::fake('s3');

    $this->team->updateBrandWatermark(UploadedFile::fake()->image('watermark.png'));

    expect($this->team->fresh()->brand_watermark_transparency)->toBeNull();

    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->set('form.watermarkTransparency', 50)
        ->call('save');

    expect($this->team->fresh()->brand_watermark_transparency)->toBe(50);
});

it('can save a text watermark', function () {
    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->set('form.watermarkType', 'text')
        ->set('form.watermarkText', 'Acme Studio')
        ->set('form.watermarkPosition', 'bottom')
        ->set('form.watermarkTransparency', 30)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('ts-ui:toast');

    $team = $this->team->fresh();

    expect($team->brand_watermark_type)->toBe('text')
        ->and($team->brand_watermark_text)->toBe('Acme Studio')
        ->and($team->brand_watermark_font)->toBeNull()
        ->and($team->brand_watermark_size)->toBe('md')
        ->and($team->brand_watermark_path)->toBeNull()
        ->and($team->brand_watermark_position)->toBe('bottom')
        ->and($team->brand_watermark_transparency)->toBe(30);
});

it('can save a text watermark with a custom font and size', function () {
    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->set('form.watermarkType', 'text')
        ->set('form.watermarkText', 'Acme Studio')
        ->set('form.watermarkFont', 'Montserrat')
        ->set('form.watermarkSize', 'xl')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('ts-ui:toast');

    $team = $this->team->fresh();

    expect($team->brand_watermark_font)->toBe('Montserrat')
        ->and($team->brand_watermark_size)->toBe('xl');
});

it('rejects an unsupported watermark font', function () {
    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->set('form.watermarkType', 'text')
        ->set('form.watermarkText', 'Acme Studio')
        ->set('form.watermarkFont', 'Comic Sans MS')
        ->call('save')
        ->assertHasErrors(['form.watermarkFont' => 'in']);

    expect($this->team->fresh()->brand_watermark_font)->toBeNull();
});

it('does not save an empty text watermark', function () {
    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->set('form.watermarkType', 'text')
        ->set('form.watermarkText', '')
        ->call('save')
        ->assertNotDispatched('ts-ui:toast');

    $team = $this->team->fresh();

    expect($team->brand_watermark_type)->toBe('image')
        ->and($team->brand_watermark_text)->toBeNull();
});

it('rejects a text watermark longer than 100 characters', function () {
    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->set('form.watermarkType', 'text')
        ->set('form.watermarkText', str_repeat('a', 101))
        ->call('save')
        ->assertHasErrors(['form.watermarkText' => 'max']);

    expect($this->team->fresh()->brand_watermark_text)->toBeNull();
});

it('can save settings when a text watermark already exists', function () {
    $this->team->update([
        'brand_watermark_type' => 'text',
        'brand_watermark_text' => 'Acme Studio',
    ]);

    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->set('form.watermarkPosition', 'bottom')
        ->set('form.watermarkTransparency', 25)
        ->call('save')
        ->assertDispatched('ts-ui:toast');

    $team = $this->team->fresh();

    expect($team->brand_watermark_type)->toBe('text')
        ->and($team->brand_watermark_text)->toBe('Acme Studio')
        ->and($team->brand_watermark_position)->toBe('bottom')
        ->and($team->brand_watermark_transparency)->toBe(25);
});

it('keeps the image available when switching to text', function () {
    Storage::fake('s3');

    $this->team->updateBrandWatermark(UploadedFile::fake()->image('watermark.png'));
    $path = $this->team->fresh()->brand_watermark_path;

    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->set('form.watermarkType', 'text')
        ->set('form.watermarkText', 'Acme Studio')
        ->call('save')
        ->assertDispatched('ts-ui:toast');

    $team = $this->team->fresh();

    expect($team->brand_watermark_type)->toBe('text')
        ->and($team->brand_watermark_text)->toBe('Acme Studio')
        ->and($team->brand_watermark_path)->toBe($path)
        ->and($team->brand_watermark_url)->toBeNull();
});

it('updates the preview to the saved image when switching back to image mode', function () {
    Storage::fake('s3');

    $this->team->updateBrandWatermark(UploadedFile::fake()->image('watermark.png'));

    $this->team->update([
        'brand_watermark_type' => 'text',
        'brand_watermark_text' => 'Acme Studio',
    ]);

    $component = Livewire::actingAs($this->user)->test('pages::branding.watermark');

    expect($component->html())->not->toContain('data-watermark-image');

    $component->set('form.watermarkType', 'image');

    expect($component->html())->toContain('data-watermark-image');
});

it('can switch back to the image watermark', function () {
    Storage::fake('s3');

    $this->team->updateBrandWatermark(UploadedFile::fake()->image('watermark.png'));

    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->set('form.watermarkType', 'text')
        ->set('form.watermarkText', 'Acme Studio')
        ->call('save');

    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->set('form.watermarkType', 'image')
        ->call('save')
        ->assertDispatched('ts-ui:toast');

    $team = $this->team->fresh();

    expect($team->brand_watermark_type)->toBe('image')
        ->and($team->brand_watermark_url)->not->toBeNull();
});

it('can delete a text watermark', function () {
    $this->team->update([
        'brand_watermark_type' => 'text',
        'brand_watermark_text' => 'Acme Studio',
        'brand_watermark_font' => 'Montserrat',
        'brand_watermark_size' => 'lg',
    ]);

    Livewire::actingAs($this->user)->test('pages::branding.watermark')
        ->call('deleteWatermark')
        ->assertDispatched('ts-ui:toast');

    $team = $this->team->fresh();

    expect($team->brand_watermark_type)->toBe('image')
        ->and($team->brand_watermark_text)->toBeNull()
        ->and($team->getRawOriginal('brand_watermark_font'))->toBeNull()
        ->and($team->brand_watermark_size)->toBe('md')
        ->and($team->brand_watermark_path)->toBeNull();
});

it('shows the saved text watermark on the branding page', function () {
    $this->team->update([
        'brand_watermark_type' => 'text',
        'brand_watermark_text' => 'Acme Studio',
    ]);

    actingAs($this->user)->get('/branding/watermark')
        ->assertSee('Acme Studio');
});

it('switches to text mode and saves the watermark in the browser', function () {
    $this->actingAs($this->user);

    $page = visit('/branding/watermark');

    $page->assertSee('Watermark type')
        ->click('input[name="form.watermarkType"][value="text"]')
        ->wait(0.5)
        ->assertVisible('input[maxlength="100"]')
        ->fill('input[maxlength="100"]', 'Acme Studio')
        ->click('input[name="form.watermarkSize"][value="xl"]')
        ->click('button[type="submit"]')
        ->wait(1)
        ->assertSee('Acme Studio');

    $team = $this->team->fresh();

    expect($team->brand_watermark_type)->toBe('text')
        ->and($team->brand_watermark_text)->toBe('Acme Studio')
        ->and($team->brand_watermark_size)->toBe('xl');
})->group('browser');
