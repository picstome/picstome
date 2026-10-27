<?php

use App\Models\User;
use App\Services\TranslationOverrideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    File::deleteDirectory(config('translation_overrides.path'));
    app('translator')->setLoaded([]);

    config(['picstome.admin_emails' => ['admin@example.com']]);

    $this->admin = User::factory()->withPersonalTeam()->create([
        'email' => 'admin@example.com',
    ]);
});

afterEach(function () {
    File::deleteDirectory(config('translation_overrides.path'));
    app('translator')->setLoaded([]);
});

it('allows an admin to view the translations panel', function () {
    actingAs($this->admin)
        ->get('/admin/translations')
        ->assertOk()
        ->assertSee('Translations')
        ->assertSee('Search by key or text...')
        ->assertSee('Key');
});

it('forbids access to the translations panel for non-admins', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'email' => 'user@example.com',
    ]);

    actingAs($user)->get('/admin/translations')->assertForbidden();
});

it('searches translations by key and value', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.translations')
        ->set('search', 'Gallery Expiration Reminder')
        ->assertSee('Gallery Expiration Reminder')
        ->assertDontSee('Verify Email Address');
});

it('filters translations missing a Spanish value', function () {
    $langPath = storage_path('framework/testing/lang-'.uniqid());

    File::ensureDirectoryExists($langPath);
    File::put($langPath.'/en.json', json_encode(['Only in English' => 'Only in English']));
    File::put($langPath.'/es.json', '{}');

    $this->app->useLangPath($langPath);

    try {
        Livewire::actingAs($this->admin)
            ->test('pages::admin.translations')
            ->set('status', 'missing')
            ->assertSee('Only in English');
    } finally {
        File::deleteDirectory($langPath);
    }
});

it('filters overridden translations', function () {
    app(TranslationOverrideService::class)->save([
        'es' => ['Hello!' => '¡Hola admin!'],
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.translations')
        ->set('status', 'overridden')
        ->assertSee('Hello!')
        ->assertDontSee('Verify Email Address');
});

it('edits a translation', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.translations')
        ->call('edit', 'Hello!')
        ->assertSet('enValue', 'Hello!')
        ->assertSet('esValue', '¡Hola!')
        ->set('esValue', '¡Hola admin!')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(TranslationOverrideService::class)->value('es', 'Hello!'))
        ->toBe('¡Hola admin!');
});

it('restores a translation to its default', function () {
    $service = app(TranslationOverrideService::class);
    $service->save(['es' => ['Hello!' => '¡Hola admin!']]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.translations')
        ->call('edit', 'Hello!')
        ->call('resetTranslation')
        ->assertSet('esValue', '¡Hola!');

    expect($service->value('es', 'Hello!'))->toBe('¡Hola!')
        ->and(File::exists($service->path('es')))->toBeFalse();
});

it('rejects translations that drop a placeholder', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.translations')
        ->call('edit', 'Booking Confirmed: :name')
        ->set('esValue', 'Reserva confirmada')
        ->call('save')
        ->assertHasErrors('esValue');
});

it('rejects placeholders that the application does not provide', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.translations')
        ->call('edit', 'Hello!')
        ->set('enValue', 'Hello :unknown!')
        ->set('esValue', '¡Hola :unknown!')
        ->call('save')
        ->assertHasErrors('enValue');
});

it('filters translations to a list of keys', function () {
    Livewire::withQueryParams(['keys' => ['Hello!']])
        ->actingAs($this->admin)
        ->test('pages::admin.translations')
        ->assertSee('Hello!')
        ->assertDontSee('Verify Email Address');
});

it('does not scroll horizontally', function (int $width) {
    $this->actingAs($this->admin);

    visit('/admin/translations')
        ->resize($width, 900)
        ->assertSee('Translations')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth + 1', true)
        ->assertScript("(() => { const el = document.querySelector('ui-table-scroll-area'); return el === null || el.scrollWidth <= el.clientWidth + 1; })()", true)
        ->assertNoJavascriptErrors();
})->with([1024, 1280, 1440, 1920]);

it('opens the editor from the browser', function () {
    $this->actingAs($this->admin);

    visit('/admin/translations')
        ->assertSee('Translations')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth + 1', true)
        ->assertScript("(() => { const el = document.querySelector('ui-table-scroll-area'); return el === null || el.scrollWidth <= el.clientWidth + 1; })()", true)
        ->fill('[data-test="translation-search"]', 'Hello!')
        ->click('[data-test="edit-'.md5('Hello!').'"]')
        ->assertVisible('[data-test="translation-editor"]')
        ->assertSee('Edit translation')
        ->assertNoJavascriptErrors();
});
