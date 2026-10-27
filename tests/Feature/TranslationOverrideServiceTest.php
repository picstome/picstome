<?php

use App\Models\Gallery;
use App\Notifications\GalleryExpirationReminder;
use App\Services\TranslationOverrideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    File::deleteDirectory(config('translation_overrides.path'));
    app('translator')->setLoaded([]);
});

afterEach(function () {
    File::deleteDirectory(config('translation_overrides.path'));
    app('translator')->setLoaded([]);
});

it('merges admin overrides over the repository translations', function () {
    $service = app(TranslationOverrideService::class);

    $service->save(['es' => ['Hello!' => '¡Hola desde el admin!']]);

    expect($service->value('es', 'Hello!'))->toBe('¡Hola desde el admin!')
        ->and($service->isOverridden('es', 'Hello!'))->toBeTrue()
        ->and(json_decode(File::get($service->path('es')), true))
        ->toBe(['Hello!' => '¡Hola desde el admin!']);

    app('translator')->setLoaded([]);
    app()->setLocale('es');

    expect(__('Hello!'))->toBe('¡Hola desde el admin!');
});

it('removes overrides when restoring the default', function () {
    $service = app(TranslationOverrideService::class);

    $service->save(['es' => ['Hello!' => '¡Hola desde el admin!']]);
    $service->reset('Hello!');

    expect($service->value('es', 'Hello!'))->toBe('¡Hola!')
        ->and($service->isOverridden('es', 'Hello!'))->toBeFalse()
        ->and(File::exists($service->path('es')))->toBeFalse();
});

it('does not store values identical to the repository translation', function () {
    $service = app(TranslationOverrideService::class);

    $service->save(['es' => ['Hello!' => '¡Hola!']]);

    expect(File::exists($service->path('es')))->toBeFalse();
});

it('does not store English values identical to the fallback key', function () {
    $service = app(TranslationOverrideService::class);

    $service->save(['en' => ['A new booking has been made.' => 'A new booking has been made.']]);

    expect(File::exists($service->path('en')))->toBeFalse();
});

it('removes empty values', function () {
    $service = app(TranslationOverrideService::class);

    $service->save(['es' => ['Hello!' => '   ']]);

    expect(File::exists($service->path('es')))->toBeFalse();
});

it('rejects locales that are not editable', function () {
    $service = app(TranslationOverrideService::class);

    expect(fn () => $service->save(['fr' => ['Hello!' => 'Bonjour']]))
        ->toThrow(RuntimeException::class, 'Unsupported translation locale [fr].');
});

it('extracts unique placeholders from a string', function () {
    $service = app(TranslationOverrideService::class);

    expect($service->placeholders('Hello :name, :name and :date'))
        ->toBe(['name', 'date']);
});

it('applies overrides to notification emails', function () {
    $gallery = Gallery::factory()->create([
        'name' => 'Test gallery',
        'expiration_date' => now()->addDays(2),
    ]);

    app(TranslationOverrideService::class)->save([
        'es' => ['Gallery Expiration Reminder' => 'Aviso de expiración'],
    ]);

    app('translator')->setLoaded([]);
    app()->setLocale('es');

    $mail = (new GalleryExpirationReminder($gallery))->toMail($gallery->team->owner);

    expect($mail->subject)->toBe('Aviso de expiración');
});
