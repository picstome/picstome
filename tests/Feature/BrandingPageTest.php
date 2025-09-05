<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('redirects main branding page to general settings', function () {
    $response = actingAs($this->user)->get('/branding');

    $response->assertRedirect('/branding/general');
});

it('allows users to see the general branding page', function () {
    $response = actingAs($this->user)->get('/branding/general');

    $response->assertStatus(200);
});

it('allows users to see the logos branding page', function () {
    $response = actingAs($this->user)->get('/branding/logos');

    $response->assertStatus(200);
});

it('allows users to see the watermark branding page', function () {
    $response = actingAs($this->user)->get('/branding/watermark');

    $response->assertStatus(200);
});

it('allows users to see the styling branding page', function () {
    $response = actingAs($this->user)->get('/branding/styling');

    $response->assertStatus(200);
});

it('prevents guests from viewing branding pages', function () {
    $response = get('/branding/general');

    $response->assertRedirect('/login');
});

it('can save a brand logo', function () {
    Storage::fake('s3');
    expect($this->team->brand_logo_path)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding.logos')
        ->set('form.logo', UploadedFile::fake()->image('logo.png'))
        ->call('save');

    expect(Team::first()->brand_logo_path)->not->toBeNull();
    expect(Team::first()->brand_logo_url)->not->toBeNull();
});

it('can save a brand logo icon', function () {
    Storage::fake('s3');
    expect($this->team->brand_logo_path)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding.logos')
        ->set('form.logoIcon', UploadedFile::fake()->image('logo.png'))
        ->call('save');

    expect(Team::first()->brand_logo_icon_path)->not->toBeNull();
    expect(Team::first()->brand_logo_icon_url)->not->toBeNull();
});

it('can save a brand watermark', function () {
    Storage::fake('s3');
    expect($this->team->brand_watermark_path)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding.watermark')
        ->set('form.watermark', UploadedFile::fake()->image('watermark.png'))
        ->call('save');

    expect(Team::first()->brand_watermark_path)->not->toBeNull();
    expect(Team::first()->brand_watermark_url)->not->toBeNull();
});

it('can change watermark position', function () {
    expect($this->team->brand_watermark_position)->not()->toBe('bottom');

    Volt::actingAs($this->user)->test('pages.branding.watermark')
        ->set('form.watermarkPosition', 'bottom')
        ->call('save');

    expect($this->team->fresh()->brand_watermark_position)->toBe('bottom');
});

it('can change brand color', function () {
    expect($this->team->brand_color)->not()->toBe('red');

    Volt::actingAs($this->user)->test('pages.branding.styling')
        ->set('form.color', 'red')
        ->call('save');

    expect($this->team->fresh()->brand_color)->toBe('red');
});

it('can change brand font', function () {
    expect($this->team->brand_font)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding.styling')
        ->set('form.font', 'Montserrat')
        ->call('save');

    expect($this->team->fresh()->brand_font)->toBe('Montserrat');
});

it('can change watermark transparency', function () {
    expect($this->team->brand_watermark_transparency)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding.watermark')
        ->set('form.watermarkTransparency', 50)
        ->call('save');

    expect($this->team->fresh()->brand_watermark_transparency)->toBe(50);
});

it('allows user to update handle successfully', function () {
    $newHandle = 'newhandle';

    Volt::actingAs($this->user)->test('pages.branding.general')
        ->set('form.handle', $newHandle)
        ->call('save');

    expect($this->team->fresh()->handle)->toBe($newHandle);
});

it('validates handle must be lowercase', function () {
    Volt::actingAs($this->user)->test('pages.branding.general')
        ->set('form.handle', 'MixedCaseHandle')
        ->call('save')
        ->assertHasErrors(['form.handle']);
});

it('prevents duplicate handles with uniqueness validation', function () {
    Team::factory()->create(['handle' => 'existinghandle']);

    Volt::actingAs($this->user)->test('pages.branding.general')
        ->set('form.handle', 'existinghandle')
        ->call('save')
        ->assertHasErrors(['form.handle']);
});

it('prevents special characters in handles', function () {
    Volt::actingAs($this->user)->test('pages.branding.general')
        ->set('form.handle', 'invalid@handle!')
        ->call('save')
        ->assertHasErrors(['form.handle']);
});

it('enforces minimum length for handles', function () {
    Volt::actingAs($this->user)->test('pages.branding.general')
        ->set('form.handle', 'a')
        ->call('save')
        ->assertHasErrors(['form.handle']);
});

it('enforces maximum length for handles', function () {
    $longHandle = str_repeat('a', 100);

    Volt::actingAs($this->user)->test('pages.branding.general')
        ->set('form.handle', $longHandle)
        ->call('save')
        ->assertHasErrors(['form.handle']);
});

it('prevents empty string handles', function () {
    Volt::actingAs($this->user)->test('pages.branding.general')
        ->set('form.handle', '')
        ->call('save')
        ->assertHasErrors(['form.handle']);
});

it('allows users to see the public profile branding page', function () {
    $response = actingAs($this->user)->get('/branding/public-profile');

    $response->assertStatus(200);
});

it('prevents guests from viewing public profile page', function () {
    $response = get('/branding/public-profile');

    $response->assertRedirect('/login');
});

it('can save a bio description', function () {
    expect($this->team->bio)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding.public-profile')
        ->set('form.bio', 'This is my bio description')
        ->call('save');

    expect($this->team->fresh()->bio)->toBe('This is my bio description');
});

it('validates bio description maximum length', function () {
    $longBio = str_repeat('a', 1001);

    Volt::actingAs($this->user)->test('pages.branding.public-profile')
        ->set('form.bio', $longBio)
        ->call('save')
        ->assertHasErrors(['form.bio']);
});

it('allows empty bio description', function () {
    $this->team->update(['bio' => 'Existing bio']);

    Volt::actingAs($this->user)->test('pages.branding.public-profile')
        ->set('form.bio', '')
        ->call('save');

    expect($this->team->fresh()->bio)->toBe('');
});

it('sanitizes html content in bio', function () {
    $htmlContent = '<p>This is <strong>bold</strong> and <em>italic</em> text.</p><script>alert("xss")</script>';

    Volt::actingAs($this->user)->test('pages.branding.public-profile')
        ->set('form.bio', $htmlContent)
        ->call('save');

    $savedBio = $this->team->fresh()->bio;
    expect($savedBio)->toContain('<strong>bold</strong>')
        ->and($savedBio)->toContain('<em>italic</em>')
        ->and($savedBio)->not->toContain('<script>')
        ->and($savedBio)->not->toContain('alert("xss")');
});

it('adds nofollow to links in bio', function () {
    $htmlContent = '<p>Check out my <a href="https://example.com">website</a> and <a href="https://google.com" rel="noopener">google</a>.</p>';

    Volt::actingAs($this->user)->test('pages.branding.public-profile')
        ->set('form.bio', $htmlContent)
        ->call('save');

    $savedBio = $this->team->fresh()->bio;

    expect($savedBio)->toContain('rel="nofollow"')
        ->and($savedBio)->toContain('<a href="https://example.com" rel="nofollow">')
        ->and($savedBio)->toContain('<a href="https://google.com" rel="nofollow">');
});

it('can save instagram link', function () {
    expect($this->team->instagram_url)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding.public-profile')
        ->set('form.instagram', 'https://instagram.com/username')
        ->call('save');

    expect($this->team->fresh()->instagram_url)->toBe('https://instagram.com/username');
});

it('can save youtube link', function () {
    expect($this->team->youtube_url)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding.public-profile')
        ->set('form.youtube', 'https://youtube.com/channel/UC123')
        ->call('save');

    expect($this->team->fresh()->youtube_url)->toBe('https://youtube.com/channel/UC123');
});

it('can save facebook link', function () {
    expect($this->team->facebook_url)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding.public-profile')
        ->set('form.facebook', 'https://facebook.com/username')
        ->call('save');

    expect($this->team->fresh()->facebook_url)->toBe('https://facebook.com/username');
});

it('can save x link', function () {
    expect($this->team->x_url)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding.public-profile')
        ->set('form.x', 'https://x.com/username')
        ->call('save');

    expect($this->team->fresh()->x_url)->toBe('https://x.com/username');
});

it('can save tiktok link', function () {
    expect($this->team->tiktok_url)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding.public-profile')
        ->set('form.tiktok', 'https://tiktok.com/@username')
        ->call('save');

    expect($this->team->fresh()->tiktok_url)->toBe('https://tiktok.com/@username');
});

it('can save twitch link', function () {
    expect($this->team->twitch_url)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding.public-profile')
        ->set('form.twitch', 'https://twitch.tv/username')
        ->call('save');

    expect($this->team->fresh()->twitch_url)->toBe('https://twitch.tv/username');
});

it('can save website link', function () {
    expect($this->team->website_url)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding.public-profile')
        ->set('form.website', 'https://example.com')
        ->call('save');

    expect($this->team->fresh()->website_url)->toBe('https://example.com');
});

it('can save other social link', function () {
    expect($this->team->other_social_links)->toBeNull();

    Volt::actingAs($this->user)->test('pages.branding.public-profile')
        ->set('form.other', ['label' => 'MySite', 'url' => 'https://mysite.com'])
        ->call('save');

    expect($this->team->fresh()->other_social_links)->toBe(['label' => 'MySite', 'url' => 'https://mysite.com']);
});

it('validates instagram url format', function () {
    Volt::actingAs($this->user)->test('pages.branding.public-profile')
        ->set('form.instagram', 'invalid-url')
        ->call('save')
        ->assertHasErrors(['form.instagram']);
});

it('validates youtube url format', function () {
    Volt::actingAs($this->user)->test('pages.branding.public-profile')
        ->set('form.youtube', 'invalid-url')
        ->call('save')
        ->assertHasErrors(['form.youtube']);
});

it('validates facebook url format', function () {
    Volt::actingAs($this->user)->test('pages.branding.public-profile')
        ->set('form.facebook', 'invalid-url')
        ->call('save')
        ->assertHasErrors(['form.facebook']);
});

it('validates x url format', function () {
    Volt::actingAs($this->user)->test('pages.branding.public-profile')
        ->set('form.x', 'invalid-url')
        ->call('save')
        ->assertHasErrors(['form.x']);
});

it('validates tiktok url format', function () {
    Volt::actingAs($this->user)->test('pages.branding.public-profile')
        ->set('form.tiktok', 'invalid-url')
        ->call('save')
        ->assertHasErrors(['form.tiktok']);
});

it('validates twitch url format', function () {
    Volt::actingAs($this->user)->test('pages.branding.public-profile')
        ->set('form.twitch', 'invalid-url')
        ->call('save')
        ->assertHasErrors(['form.twitch']);
});

it('validates website url format', function () {
    Volt::actingAs($this->user)->test('pages.branding.public-profile')
        ->set('form.website', 'invalid-url')
        ->call('save')
        ->assertHasErrors(['form.website']);
});

it('validates other social link url format', function () {
    Volt::actingAs($this->user)->test('pages.branding.public-profile')
        ->set('form.other', ['label' => 'MySite', 'url' => 'invalid-url'])
        ->call('save')
        ->assertHasErrors(['form.other.url']);
});

it('allows empty social links', function () {
    $this->team->update(['instagram_url' => 'https://instagram.com/old']);

    Volt::actingAs($this->user)->test('pages.branding.public-profile')
        ->set('form.instagram', '')
        ->call('save');

    expect($this->team->fresh()->instagram_url)->toBe('');
});


