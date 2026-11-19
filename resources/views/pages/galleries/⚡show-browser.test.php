<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\withoutDefer;

uses(RefreshDatabase::class);

beforeEach(function () {
    withoutDefer();

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('the photographer gallery refreshes when the watcher detects a new photo', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

    expect($component->html())->toContain('wire:poll.5s');

    $photo = Photo::factory()->for($gallery)->create();

    $component->call('refreshPhotos');

    expect($component->allPhotos->contains($photo))->toBeTrue();
});

test('owner gallery renders a bounded page and loads more photos on demand', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    Photo::factory()->count(65)->for($gallery)->create(['status' => 'processed']);

    $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

    expect($component->allPhotos)->toHaveCount(60)
        ->and(substr_count($component->html(), 'data-photo-id='))->toBe(60)
        ->and($component->html())->toContain('x-on:scroll.window.throttle.150ms')
        ->and($component->html())->not->toContain('wire:name="photo-item"');

    $component->call('loadMore', 'all');

    expect($component->allPhotos)->toHaveCount(65);
});

test('owner gallery reveals the photo referenced by the viewer back link', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    Photo::factory()->count(65)->for($gallery)->sequence(fn ($sequence) => [
        'name' => 'photo-'.($sequence->index + 1).'.jpg',
    ])->create(['status' => 'processed']);

    $target = $gallery->photos()->where('name', 'photo-65.jpg')->firstOrFail();

    $component = Livewire::actingAs($this->user)
        ->withQueryParams(['photo' => $target->id])
        ->test('pages::galleries.show', ['gallery' => $gallery]);

    expect($component->allPhotos)->toHaveCount(65)
        ->and($component->allPhotos->contains($target))->toBeTrue()
        ->and($component->get('allPhotoLimit'))->toBe(120);
});

test('owner gallery reveals a favorited photo referenced by the viewer back link', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    Photo::factory()->count(65)->for($gallery)->favorited()->sequence(fn ($sequence) => [
        'name' => 'photo-'.($sequence->index + 1).'.jpg',
    ])->create(['status' => 'processed']);

    $target = $gallery->photos()->where('name', 'photo-65.jpg')->firstOrFail();

    $component = Livewire::actingAs($this->user)
        ->withQueryParams(['activeTab' => 'favorited', 'photo' => $target->id])
        ->test('pages::galleries.show', ['gallery' => $gallery]);

    expect($component->get('activeTab'))->toBe('all')
        ->and($component->get('favoritesOnly'))->toBeTrue()
        ->and($component->allPhotos)->toHaveCount(65)
        ->and($component->allPhotos->contains($target))->toBeTrue()
        ->and($component->get('allPhotoLimit'))->toBe(120);
});

test('going back from a photo beyond the first page scrolls to it', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    Photo::factory()->count(65)->for($gallery)->sequence(fn ($sequence) => [
        'name' => 'photo-'.($sequence->index + 1).'.jpg',
    ])->create(['status' => 'processed']);

    $target = $gallery->photos()->where('name', 'photo-65.jpg')->firstOrFail();

    $this->actingAs($this->user);

    $page = visit('/galleries/'.$gallery->id.'/photos/'.$target->id);

    waitForBrowserValue($page, <<<'JS'
        return document.querySelector('[data-photo-back]') ? 1 : null;
    JS, 10000);

    $page->click('[data-photo-back]');
    waitForPhotoCardCount($page, 65);

    $scrolled = waitForBrowserValue($page, <<<JS
        const el = document.querySelector('#photo-{$target->id}');
        if (! el) return null;
        const rect = el.getBoundingClientRect();

        return rect.top >= 0 && rect.top < window.innerHeight && window.scrollY > 0 ? 1 : null;
    JS, 10000);

    expect($scrolled)->toBe(1);

    $page->assertNoJavaScriptErrors();

    expect($page->script('window.location.hash'))->toBe('#photo-'.$target->id)
        ->and($page->script("document.querySelectorAll('[data-photo-card]').length"))->toBe(65)
        ->and($page->script("(() => { const el = document.querySelector('#photo-{$target->id}'); if (! el) return false; const rect = el.getBoundingClientRect(); return rect.top >= 0 && rect.top < window.innerHeight; })()"))->toBeTrue()
        ->and($page->script('window.scrollY'))->toBeGreaterThan(0);
})->group('browser');

test('owner gallery loads the next batch automatically near the bottom', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    Photo::factory()->count(65)->for($gallery)->create(['status' => 'processed']);

    $this->actingAs($this->user);

    $page = visit('/galleries/'.$gallery->id);
    waitForAlpine($page, '[role=tablist] button');

    $page->assertNoJavaScriptErrors();

    $initial = waitForBrowserValue($page, <<<'JS'
        return document.querySelectorAll('[data-photo-card]').length === 60 ? 1 : null;
    JS, 15000);

    expect($initial)->toBe(1);

    $page->script('window.scrollTo(0, document.body.scrollHeight)');

    $loaded = waitForBrowserValue($page, <<<'JS'
        return document.querySelectorAll('[data-photo-card]').length === 65 ? 1 : null;
    JS, 20000);

    expect($loaded)->toBe(1);
})->group('browser');

test('owner can switch between photos and download tabs', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    Photo::factory()->for($gallery)->create(['status' => 'processed']);

    $this->actingAs($this->user);

    $page = visit('/galleries/'.$gallery->id);
    waitForAlpine($page, '[role=tablist] button');

    $page->click('[role=tablist] button[aria-label="Download"]');

    $downloaded = waitForBrowserValue($page, <<<'JS'
        return document.body.innerText.includes('Download gallery') ? 1 : null;
    JS, 30000);

    expect($downloaded)->toBe(1);

    $page->click('[role=tablist] button[aria-label="Photos"]');

    $photos = waitForBrowserValue($page, <<<'JS'
        return document.querySelectorAll('[data-photo-card]').length === 1 ? 1 : null;
    JS, 30000);

    expect($photos)->toBe(1);
})->group('browser');

it('moves files selected in the add media modal to the upload dock', function () {
    Storage::fake('s3');

    $gallery = Gallery::factory()->for($this->team)->create();

    $this->actingAs($this->user);

    $page = visit('/galleries/'.$gallery->id);

    waitForAlpineRoot($page, '[x-data="galleryDrop"]');

    waitForBrowserValue($page, <<<'JS'
        return window.PicstomeUploads ? 1 : null;
    JS, 10000);

    $page->click('Add media');

    // The browser runs on a remote Playwright server, so local file paths
    // cannot be attached. Dispatch a real File through the input instead.
    $page->script(<<<'JS'
        const input = document.querySelector('#add-photos-uploader input[type=file]');
        const file = new File([new Uint8Array(200 * 1024)], 'modal.jpg', { type: 'image/jpeg' });
        const data = new DataTransfer();
        data.items.add(file);
        input.files = data.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    JS);

    waitForBrowserValue($page, <<<'JS'
        return window.PicstomeUploads.entries.length > 0 ? 1 : null;
    JS, 10000);

    $page->assertVisible('@upload-dock')
        ->assertSee('modal.jpg')
        ->assertNoJavascriptErrors();
})->group('browser');

it('shows files dropped anywhere in the gallery in the upload dock', function () {
    Storage::fake('s3');

    $gallery = Gallery::factory()->for($this->team)->create();

    $this->actingAs($this->user);

    $page = visit('/galleries/'.$gallery->id);

    waitForAlpineRoot($page, '[x-data="galleryDrop"]');

    $page->script(<<<'JS'
        const file = new File([new Uint8Array(64 * 1024)], 'drop.jpg', { type: 'image/jpeg' });
        const data = new DataTransfer();
        data.items.add(file);
        const event = new Event('drop', { bubbles: true, cancelable: true });
        Object.defineProperty(event, 'dataTransfer', { value: data });
        window.dispatchEvent(event);
    JS);

    waitForBrowserValue($page, <<<'JS'
        return window.PicstomeUploads.entries.length === 1 ? 1 : null;
    JS, 10000);

    $page->assertVisible('@upload-dock')
        ->assertSee('drop.jpg')
        ->assertNoJavascriptErrors();

    $page->click('@upload-dock-minimize');

    waitForBrowserValue($page, <<<'JS'
        const chip = document.querySelector('[data-test=upload-dock-chip]');

        return chip && getComputedStyle(chip).display !== 'none' ? 1 : null;
    JS, 10000);

    $page->assertVisible('@upload-dock-chip')->assertNoJavascriptErrors();

    $page->click('@upload-dock-chip');

    waitForBrowserValue($page, <<<'JS'
        const dock = document.querySelector('[data-test=upload-dock]');

        return dock && getComputedStyle(dock).display !== 'none' ? 1 : null;
    JS, 10000);

    $page->assertVisible('@upload-dock')->assertNoJavascriptErrors();
})->group('browser');

it('keeps the upload dock alive across in-app navigation', function () {
    Storage::fake('s3');

    $gallery = Gallery::factory()->for($this->team)->create();

    $this->actingAs($this->user);

    $page = visit('/galleries/'.$gallery->id);

    waitForAlpineRoot($page, '[x-data="galleryDrop"]');

    $page->script(<<<'JS'
        const file = new File([new Uint8Array(2 * 1024 * 1024)], 'navigation.jpg', { type: 'image/jpeg' });
        const data = new DataTransfer();
        data.items.add(file);
        const event = new Event('drop', { bubbles: true, cancelable: true });
        Object.defineProperty(event, 'dataTransfer', { value: data });
        window.dispatchEvent(event);
    JS);

    waitForBrowserValue($page, <<<'JS'
        return window.PicstomeUploads.entries.length === 1 ? 1 : null;
    JS, 10000);

    $page->click('nav[aria-label="Sidebar"] a[href="'.route('dashboard').'"]');

    $entryCount = waitForBrowserValue($page, <<<'JS'
        return window.PicstomeUploads.entries.length === 1 ? 1 : null;
    JS, 15000);

    $dockVisible = waitForBrowserValue($page, <<<'JS'
        const dock = document.querySelector('[data-test=upload-dock]');

        return dock && getComputedStyle(dock).display !== 'none' ? 1 : null;
    JS, 15000);

    expect($entryCount)->toBe(1)
        ->and($dockVisible)->toBe(1);

    $page->assertSee('navigation.jpg')
        ->assertNoJavascriptErrors();
})->group('browser');

it('asks before closing the dock while files are still pending', function () {
    Storage::fake('s3');

    $gallery = Gallery::factory()->for($this->team)->create(['name' => 'Just a Gallery']);

    $this->actingAs($this->user);

    $page = visit('/galleries/'.$gallery->id);

    waitForAlpineRoot($page, '[x-data="galleryDrop"]');

    $page->script(<<<'JS'
        const store = window.PicstomeUploads;
        const galleryId = String(document.querySelector('[data-gallery-id]').dataset.galleryId);

        store.entries.push({
            id: 8001,
            name: 'pending.jpg',
            size: 1024,
            galleryId,
            progress: 40,
            status: 'uploading',
            error: null,
            photoId: null,
            photoStatus: null,
            conflictAction: null,
            previewUrl: null,
        });
        store.open = true;
        store.collapsed = false;
    JS);

    $page->click('@upload-dock-close');

    $prompt = waitForBrowserValue($page, <<<'JS'
        const prompt = document.querySelector('[data-test=upload-close-prompt]');

        return prompt && getComputedStyle(prompt).display !== 'none' ? 1 : null;
    JS, 10000);

    expect($prompt)->toBe(1);

    $page->assertVisible('@upload-close-prompt')
        ->assertSee('1 photo left, if you close it will stop uploading')
        ->assertSee('Stop and close')
        ->assertNoJavascriptErrors();

    $page->click('Stop and close');

    $closed = waitForBrowserValue($page, <<<'JS'
        const dock = document.querySelector('[data-test=upload-dock]');
        const prompt = document.querySelector('[data-test=upload-close-prompt]');

        return window.PicstomeUploads.entries.length === 0
            && dock && getComputedStyle(dock).display === 'none'
            && prompt && getComputedStyle(prompt).display === 'none'
            ? 1
            : null;
    JS, 10000);

    expect($closed)->toBe(1);

    $page->assertNoJavascriptErrors();
})->group('browser');

it('shows the uploaded summary with a link to the gallery', function () {
    Storage::fake('s3');

    $gallery = Gallery::factory()->for($this->team)->create(['name' => 'Just a Gallery']);

    $this->actingAs($this->user);

    $page = visit('/galleries/'.$gallery->id);

    waitForAlpineRoot($page, '[x-data="galleryDrop"]');

    $page->script(<<<'JS'
        const store = window.PicstomeUploads;
        const galleryId = String(document.querySelector('[data-gallery-id]').dataset.galleryId);

        store.galleries[galleryId] = { name: 'Just a Gallery', url: '/galleries/' + galleryId };
        store.entries.push({
            id: 8002,
            name: 'ready.jpg',
            size: 1024,
            galleryId,
            progress: 100,
            status: 'processed',
            error: null,
            photoId: 123,
            photoStatus: 'processed',
            conflictAction: null,
            previewUrl: null,
        });
        store.completedAt = Date.now();
        store.open = true;
        store.collapsed = false;
    JS);

    $summary = waitForBrowserValue($page, <<<'JS'
        const dock = document.querySelector('[data-test=upload-dock]');

        return dock
            && getComputedStyle(dock).display !== 'none'
            && document.body.innerText.includes('View gallery')
            ? 1
            : null;
    JS, 10000);

    expect($summary)->toBe(1);

    $page->assertVisible('@upload-dock')
        ->assertSee('1 photo uploaded to the gallery Just a Gallery')
        ->assertSee('View gallery')
        ->assertNoJavascriptErrors();

    $page->click('@upload-dock-dismiss');

    $dismissed = waitForBrowserValue($page, <<<'JS'
        const dock = document.querySelector('[data-test=upload-dock]');

        return dock && getComputedStyle(dock).display === 'none' ? 1 : null;
    JS, 10000);

    expect($dismissed)->toBe(1);

    $page->assertNoJavascriptErrors();
})->group('browser');
