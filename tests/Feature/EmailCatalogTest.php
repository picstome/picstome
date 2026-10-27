<?php

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\File;

it('catalogues every notification class', function () {
    $notifications = collect(File::files(app_path('Notifications')))
        ->map(fn ($file): string => 'App\\Notifications\\'.$file->getBasename('.php'))
        ->filter(fn (string $class): bool => class_exists($class) && is_subclass_of($class, Notification::class))
        ->sort()
        ->values();

    $catalogued = collect(config('emails'))
        ->pluck('notification')
        ->sort()
        ->values();

    expect($catalogued->all())->toBe($notifications->all());
});

it('keeps every email text in the translation files', function () {
    $known = collect(['en', 'es'])
        ->flatMap(fn (string $locale): array => array_keys(json_decode(File::get(lang_path("{$locale}.json")), true)))
        ->unique()
        ->values();

    foreach (config('emails') as $email) {
        foreach ([...$email['fields'], ...($email['banner_fields'] ?? [])] as $field) {
            expect($known)->toContain($field['key']);
        }
    }
});

it('includes the dashboard banner copy in the storage email', function () {
    $storage = config('emails.storage_almost_full');

    expect($storage)->not->toBeNull()
        ->and($storage['banner_fields'])->not->toBeEmpty()
        ->and(collect($storage['banner_fields'])->pluck('key')->all())
        ->toContain('Your storage is almost full', 'See plans', 'Dismiss');
});
