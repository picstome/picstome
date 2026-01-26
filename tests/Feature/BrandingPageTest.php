<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

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

it('can reset dismissed setup steps from general branding page', function () {
    $this->team->dismissed_setup_steps = ['portfolio', 'payments'];
    $this->team->save();

    expect($this->team->dismissed_setup_steps)->toContain('portfolio');
    expect($this->team->dismissed_setup_steps)->toContain('payments');

    Livewire::actingAs($this->user)->test('pages.branding.general')
        ->call('resetDismissedSetupSteps');

    $this->team->refresh();
    expect($this->team->dismissed_setup_steps)->toBe([]);
});

it('can save a brand logo', function () {
    Storage::fake('s3');
    expect($this->team->brand_logo_path)->toBeNull();

    Livewire::actingAs($this->user)->test('pages.branding.logos')
        ->set('form.logo', UploadedFile::fake()->image('logo.png'))
        ->call('save');

    expect(Team::first()->brand_logo_path)->not->toBeNull();
    expect(Team::first()->brand_logo_url)->not->toBeNull();
});

it('can save a brand logo icon', function () {
    Storage::fake('s3');
    expect($this->team->brand_logo_path)->toBeNull();

    Livewire::actingAs($this->user)->test('pages.branding.logos')
        ->set('form.logoIcon', UploadedFile::fake()->image('logo.png'))
        ->call('save');

    expect(Team::first()->brand_logo_icon_path)->not->toBeNull();
    expect(Team::first()->brand_logo_icon_url)->not->toBeNull();
});

it('can save a brand watermark', function () {
    Storage::fake('s3');
    expect($this->team->brand_watermark_path)->toBeNull();

    Livewire::actingAs($this->user)->test('pages.branding.watermark')
        ->set('form.watermark', UploadedFile::fake()->image('watermark.png'))
        ->call('save');

    expect($this->team->fresh()->brand_watermark_path)->not->toBeNull();
    expect(Team::first()->brand_watermark_url)->not->toBeNull();
});

it('can change watermark position', function () {
    expect($this->team->brand_watermark_position)->not()->toBe('bottom');

    Livewire::actingAs($this->user)->test('pages.branding.watermark')
        ->set('form.watermarkPosition', 'bottom')
        ->call('save');

    expect($this->team->fresh()->brand_watermark_position)->toBe('bottom');
});

it('can change brand color', function () {
    expect($this->team->brand_color)->not()->toBe('red');

    Livewire::actingAs($this->user)->test('pages.branding.styling')
        ->set('form.color', 'red')
        ->call('save');

    expect($this->team->fresh()->brand_color)->toBe('red');
});

it('can change brand font', function () {
    expect($this->team->brand_font)->toBeNull();

    Livewire::actingAs($this->user)->test('pages.branding.styling')
        ->set('form.font', 'Montserrat')
        ->call('save');

    expect($this->team->fresh()->brand_font)->toBe('Montserrat');
});

it('can change watermark transparency', function () {
    expect($this->team->brand_watermark_transparency)->toBeNull();

    Livewire::actingAs($this->user)->test('pages.branding.watermark')
        ->set('form.watermarkTransparency', 50)
        ->call('save');

    expect($this->team->fresh()->brand_watermark_transparency)->toBe(50);
});

it('allows user to update handle successfully', function () {
    $newHandle = 'newhandle';

    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('form.handle', $newHandle)
        ->call('save');

    expect($this->team->fresh()->handle)->toBe($newHandle);
});

it('validates handle must be lowercase', function () {
    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('form.handle', 'MixedCaseHandle')
        ->call('save')
        ->assertHasErrors(['form.handle']);
});

it('prevents duplicate handles with uniqueness validation', function () {
    Team::factory()->create(['handle' => 'existinghandle']);

    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('form.handle', 'existinghandle')
        ->call('save')
        ->assertHasErrors(['form.handle']);
});

it('prevents special characters in handles', function () {
    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('form.handle', 'invalid@handle!')
        ->call('save')
        ->assertHasErrors(['form.handle']);
});

it('enforces minimum length for handles', function () {
    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('form.handle', 'a')
        ->call('save')
        ->assertHasErrors(['form.handle']);
});

it('enforces maximum length for handles', function () {
    $longHandle = str_repeat('a', 100);

    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('form.handle', $longHandle)
        ->call('save')
        ->assertHasErrors(['form.handle']);
});

it('prevents empty string handles', function () {
    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('form.handle', '')
        ->call('save')
        ->assertHasErrors(['form.handle']);
});

it('allows users to see the public profile branding page', function () {
    $response = actingAs($this->user)->get('/public-profile');

    $response->assertStatus(200);
});

it('prevents guests from viewing public profile page', function () {
    $response = get('/public-profile');

    $response->assertRedirect('/login');
});

it('can save a bio description', function () {
    expect($this->team->bio)->toBeNull();

    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('form.bio', 'This is my bio description')
        ->call('save');

    expect($this->team->fresh()->bio)->toBe('This is my bio description');
});

it('validates bio description maximum length', function () {
    $longBio = str_repeat('a', 1001);

    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('form.bio', $longBio)
        ->call('save')
        ->assertHasErrors(['form.bio']);
});

it('allows empty bio description', function () {
    $this->team->update(['bio' => 'Existing bio']);

    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('form.bio', '')
        ->call('save');

    expect($this->team->fresh()->bio)->toBe('');
});

it('sanitizes html content in bio', function () {
    $htmlContent = '<p>This is <strong>bold</strong> and <em>italic</em> text.</p><script>alert("xss")</script>';

    Livewire::actingAs($this->user)->test('pages.public-profile')
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

    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('form.bio', $htmlContent)
        ->call('save');

    $savedBio = $this->team->fresh()->bio;

    expect($savedBio)->toContain('rel="nofollow"')
        ->and($savedBio)->toContain('<a href="https://example.com" rel="nofollow">')
        ->and($savedBio)->toContain('<a href="https://google.com" rel="nofollow">');
});

it('can save instagram link', function () {
    expect($this->team->instagram_handle)->toBeNull();

    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('socialLinksForm.instagram', 'username')
        ->call('saveSocialLinks');

    $team = $this->team->fresh();
    expect($team->instagram_handle)->toBe('username');
    expect($team->instagram_url)->toBe('https://instagram.com/username');
});

it('can save youtube link', function () {
    expect($this->team->youtube_handle)->toBeNull();

    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('socialLinksForm.youtube', 'channel/UC123')
        ->call('saveSocialLinks');

    $team = $this->team->fresh();
    expect($team->youtube_handle)->toBe('channel/UC123');
    expect($team->youtube_url)->toBe('https://youtube.com/channel/UC123');
});

it('can save facebook link', function () {
    expect($this->team->facebook_handle)->toBeNull();

    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('socialLinksForm.facebook', 'username')
        ->call('saveSocialLinks');

    $team = $this->team->fresh();
    expect($team->facebook_handle)->toBe('username');
    expect($team->facebook_url)->toBe('https://facebook.com/username');
});

it('can save x link', function () {
    expect($this->team->x_handle)->toBeNull();

    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('socialLinksForm.x', 'username')
        ->call('saveSocialLinks');

    $team = $this->team->fresh();
    expect($team->x_handle)->toBe('username');
    expect($team->x_url)->toBe('https://x.com/username');
});

it('can save tiktok link', function () {
    expect($this->team->tiktok_handle)->toBeNull();

    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('socialLinksForm.tiktok', 'username')
        ->call('saveSocialLinks');

    $team = $this->team->fresh();
    expect($team->tiktok_handle)->toBe('username');
    expect($team->tiktok_url)->toBe('https://tiktok.com/@username');
});

it('can save twitch link', function () {
    expect($this->team->twitch_handle)->toBeNull();

    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('socialLinksForm.twitch', 'username')
        ->call('saveSocialLinks');

    $team = $this->team->fresh();
    expect($team->twitch_handle)->toBe('username');
    expect($team->twitch_url)->toBe('https://twitch.tv/username');
});

it('can save website link', function () {
    expect($this->team->website_url)->toBeNull();

    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('socialLinksForm.website', 'https://example.com')
        ->call('saveSocialLinks');

    $team = $this->team->fresh();
    expect($team->website_url)->toBe('https://example.com');
    expect($team->website_url)->toBe('https://example.com'); // Same for website
});

it('can save other social link', function () {
    expect($this->team->other_social_links)->toBeNull();

    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('socialLinksForm.other', ['label' => 'MySite', 'url' => 'https://mysite.com'])
        ->call('saveSocialLinks');

    expect($this->team->fresh()->other_social_links)->toBe(['label' => 'MySite', 'url' => 'https://mysite.com']);
});

it('validates instagram handle length', function () {
    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('socialLinksForm.instagram', str_repeat('a', 256))
        ->call('saveSocialLinks')
        ->assertHasErrors(['socialLinksForm.instagram']);
});

it('validates youtube handle length', function () {
    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('socialLinksForm.youtube', str_repeat('a', 256))
        ->call('saveSocialLinks')
        ->assertHasErrors(['socialLinksForm.youtube']);
});

it('validates facebook handle length', function () {
    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('socialLinksForm.facebook', str_repeat('a', 256))
        ->call('saveSocialLinks')
        ->assertHasErrors(['socialLinksForm.facebook']);
});

it('validates x handle length', function () {
    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('socialLinksForm.x', str_repeat('a', 256))
        ->call('saveSocialLinks')
        ->assertHasErrors(['socialLinksForm.x']);
});

it('validates tiktok handle length', function () {
    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('socialLinksForm.tiktok', str_repeat('a', 256))
        ->call('saveSocialLinks')
        ->assertHasErrors(['socialLinksForm.tiktok']);
});

it('validates twitch handle length', function () {
    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('socialLinksForm.twitch', str_repeat('a', 256))
        ->call('saveSocialLinks')
        ->assertHasErrors(['socialLinksForm.twitch']);
});

it('validates website url format', function () {
    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('socialLinksForm.website', 'invalid-url')
        ->call('saveSocialLinks')
        ->assertHasErrors(['socialLinksForm.website']);
});

it('validates other social link url format', function () {
    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('socialLinksForm.other', ['label' => 'MySite', 'url' => 'invalid-url'])
        ->call('saveSocialLinks')
        ->assertHasErrors(['socialLinksForm.other.url']);
});

it('allows empty social links', function () {
    $this->team->update(['instagram_handle' => 'old']);

    Livewire::actingAs($this->user)->test('pages.public-profile')
        ->set('socialLinksForm.instagram', '')
        ->call('saveSocialLinks');

    $team = $this->team->fresh();
    expect($team->instagram_handle)->toBeNull();
    expect($team->instagram_url)->toBeNull();
});

it('loads handles from database when form initializes', function () {
    $this->team->update([
        'instagram_handle' => 'testuser',
        'tiktok_handle' => 'testuser',
        'youtube_handle' => 'channel/UC123',
    ]);

    $component = Livewire::actingAs($this->user)->test('pages.public-profile');

    expect($component->get('socialLinksForm.instagram'))->toBe('testuser');
    expect($component->get('socialLinksForm.tiktok'))->toBe('testuser');
    expect($component->get('socialLinksForm.youtube'))->toBe('channel/UC123');
});
