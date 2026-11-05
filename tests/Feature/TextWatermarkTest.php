<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoVersion;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

function textWatermarkTeam(Team $team, string $text = 'Acme Watermark', ?string $font = null, string $size = 'md'): Team
{
    $team->update([
        'brand_watermark_type' => 'text',
        'brand_watermark_text' => $text,
        'brand_watermark_font' => $font,
        'brand_watermark_size' => $size,
        'brand_watermark_transparency' => 40,
    ]);

    return $team->fresh();
}

it('renders a text watermark on shared gallery thumbnails', function () {
    textWatermarkTeam($this->team);

    $gallery = Gallery::factory()->shared()->watermarked()->for($this->team)->create();
    Photo::factory()->for($gallery)->create(['status' => 'processed']);

    Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug])
        ->assertSee('Acme Watermark')
        ->assertSee('opacity: 0.6', false);
});

it('does not render the text watermark when the gallery is not watermarked', function () {
    textWatermarkTeam($this->team);

    $gallery = Gallery::factory()->shared()->for($this->team)->create();
    Photo::factory()->for($gallery)->create(['status' => 'processed']);

    Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug])
        ->assertDontSee('Acme Watermark');
});

it('renders a text watermark in the shared photo lightbox', function () {
    textWatermarkTeam($this->team, 'Acme Watermark', 'Montserrat', 'xl');

    $gallery = Gallery::factory()->shared()->watermarked()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create(['status' => 'processed']);

    Livewire::test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->assertSee('Acme Watermark')
        ->assertSee('text-shadow', false)
        ->assertSee('watermarkFontFamily', false)
        ->assertSee('watermarkFontSize', false);
});

it('renders a repeated text watermark as an svg pattern in the lightbox', function () {
    textWatermarkTeam($this->team, 'Acme Watermark', 'Montserrat', 'xl');
    $this->team->update(['brand_watermark_position' => 'repeated']);

    $gallery = Gallery::factory()->shared()->watermarked()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create(['status' => 'processed']);

    Livewire::test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->assertSee('Acme Watermark')
        ->assertSee('patternTransform="rotate(-20)"', false)
        ->assertSee('font-size="91"', false);
});

it('renders a text watermark in the shared version comparison', function () {
    config(['picstome.file_versions_enabled' => true]);

    textWatermarkTeam($this->team, 'Acme Watermark', 'Montserrat', 'lg');

    $gallery = Gallery::factory()->shared()->watermarked()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create([
        'path' => 'galleries/current.jpg',
        'version_count' => 2,
    ]);
    PhotoVersion::factory()->for($photo)->create([
        'version_number' => 1,
        'path' => 'galleries/old.jpg',
    ]);

    Livewire::test('photo-versions', ['photo' => $photo, 'manageable' => false])
        ->assertSee('Acme Watermark')
        ->assertSee('font-size: 51px', false);
});

it('renders repeated text watermarks as a tiled svg pattern in photo tiles', function () {
    textWatermarkTeam($this->team);
    $this->team->update(['brand_watermark_position' => 'repeated']);

    $gallery = Gallery::factory()->shared()->watermarked()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create(['status' => 'processed']);

    $html = Blade::render('<x-photo-tile :photo="$photo" :gallery="$gallery" context="shared" />', [
        'photo' => $photo,
        'gallery' => $gallery,
    ]);

    expect($html)
        ->toContain('Acme Watermark')
        ->toContain('patternTransform="rotate(-20)"');
});

it('escapes html in the text watermark', function () {
    textWatermarkTeam($this->team, '<script>alert(1)</script>');

    $gallery = Gallery::factory()->shared()->watermarked()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create(['status' => 'processed']);

    $html = Blade::render('<x-photo-tile :photo="$photo" :gallery="$gallery" context="shared" />', [
        'photo' => $photo,
        'gallery' => $gallery,
    ]);

    expect($html)
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->not->toContain('<script>alert(1)</script>');
});

it('renders an image watermark on shared gallery thumbnails', function () {
    $this->team->update([
        'brand_watermark_type' => 'image',
        'brand_watermark_path' => 'teams/watermark.png',
    ]);

    $gallery = Gallery::factory()->shared()->watermarked()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create(['status' => 'processed']);

    $html = Blade::render('<x-photo-tile :photo="$photo" :gallery="$gallery" context="shared" />', [
        'photo' => $photo,
        'gallery' => $gallery,
    ]);

    expect($html)
        ->toContain('watermark.png')
        ->not->toContain('text-shadow');
});

it('loads the selected watermark font on the shared gallery and lightbox', function () {
    textWatermarkTeam($this->team, 'Acme Watermark', 'Montserrat', 'xl');

    $gallery = Gallery::factory()->shared()->watermarked()->for($this->team)->create(['ulid' => 'FONT123']);
    $photo = Photo::factory()->for($gallery)->create(['status' => 'processed']);

    get('/shares/FONT123/'.$gallery->slug)
        ->assertSuccessful()
        ->assertSee('family=Montserrat', false);

    get('/shares/FONT123/photos/'.$photo->id)
        ->assertSuccessful()
        ->assertSee('family=Montserrat', false);
});

it('renders the configured watermark font and size', function () {
    textWatermarkTeam($this->team, 'Acme Watermark', 'Montserrat', 'xl');

    $gallery = Gallery::factory()->shared()->watermarked()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create(['status' => 'processed']);

    $html = Blade::render('<x-photo-tile :photo="$photo" :gallery="$gallery" context="shared" />', [
        'photo' => $photo,
        'gallery' => $gallery,
    ]);

    expect($html)
        ->toContain('Montserrat')
        ->toContain('font-size: 52px');
});

it('uses the medium size and system font by default', function () {
    textWatermarkTeam($this->team);

    $gallery = Gallery::factory()->shared()->watermarked()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create(['status' => 'processed']);

    $html = Blade::render('<x-photo-tile :photo="$photo" :gallery="$gallery" context="shared" />', [
        'photo' => $photo,
        'gallery' => $gallery,
    ]);

    expect($html)
        ->toContain('font-size: 26px')
        ->toContain('ui-sans-serif');
});

it('renders repeated text with the configured font size', function () {
    textWatermarkTeam($this->team, 'Acme Watermark', 'Montserrat', 'xl');
    $this->team->update(['brand_watermark_position' => 'repeated']);

    $gallery = Gallery::factory()->shared()->watermarked()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create(['status' => 'processed']);

    $html = Blade::render('<x-photo-tile :photo="$photo" :gallery="$gallery" context="shared" />', [
        'photo' => $photo,
        'gallery' => $gallery,
    ]);

    expect($html)
        ->toContain('font-size="52"')
        ->toContain('Montserrat');
});

it('converts watermark transparency to css opacity', function () {
    expect($this->team->brand_watermark_opacity)->toBe(1.0);

    $this->team->update(['brand_watermark_transparency' => 40]);

    expect($this->team->fresh()->brand_watermark_opacity)->toBe(0.6);
});

it('hides the image url when the text watermark is active', function () {
    $team = Team::factory()->withWatermark()->create();

    expect($team->hasBrandWatermark())->toBeTrue()
        ->and($team->brand_watermark_url)->not->toBeNull();

    $team->update([
        'brand_watermark_type' => 'text',
        'brand_watermark_text' => 'Acme Watermark',
    ]);

    expect($team->fresh()->hasBrandWatermark())->toBeTrue()
        ->and($team->fresh()->brand_watermark_url)->toBeNull();
});
