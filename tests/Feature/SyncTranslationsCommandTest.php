<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;

it('adds missing translation keys found in Blade files to lang JSON files', function () {
    $filesystem = new Filesystem;

    $bladeTestDir = resource_path('views/test_translations');
    $bladeTestFile = $bladeTestDir.'/test.blade.php';
    $langTestDir = storage_path('framework/testing/translations');
    $langTestFile = $langTestDir.'/test.json';
    $uniqueTranslationKey = 'Unique Test Key '.uniqid();

    $filesystem->ensureDirectoryExists($bladeTestDir);
    $filesystem->put($bladeTestFile, "{{ __('$uniqueTranslationKey') }}");

    $filesystem->ensureDirectoryExists($langTestDir);
    $filesystem->put($langTestFile, json_encode([
        'Existing Key' => 'Existing Value',
    ], JSON_PRETTY_PRINT));

    $this->app->useLangPath($langTestDir);

    try {
        Artisan::call('translations:sync');

        $langJson = json_decode($filesystem->get($langTestFile), true);
        expect($langJson)->toHaveKey($uniqueTranslationKey);
        expect($langJson[$uniqueTranslationKey])->toBe($uniqueTranslationKey);
    } finally {
        $filesystem->delete($bladeTestFile);
        $filesystem->deleteDirectory($bladeTestDir);
        $filesystem->deleteDirectory($langTestDir);
    }
});
