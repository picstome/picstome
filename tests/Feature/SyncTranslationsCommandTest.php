<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;

it('adds missing translation keys found in Blade files to lang JSON files', function () {
    $filesystem = new Filesystem;

    $bladeTestDir = resource_path('views/test_translations');
    $bladeTestFile = $bladeTestDir.'/test.blade.php';
    $langTestFile = base_path('lang/test.json');
    $uniqueTranslationKey = 'Unique Test Key '.uniqid();

    $filesystem->ensureDirectoryExists($bladeTestDir);
    $filesystem->put($bladeTestFile, "{{ __('$uniqueTranslationKey') }}");

    $filesystem->put($langTestFile, json_encode([
        'Existing Key' => 'Existing Value',
    ], JSON_PRETTY_PRINT));

    Artisan::call('translations:sync');

    $langJson = json_decode($filesystem->get($langTestFile), true);
    expect($langJson)->toHaveKey($uniqueTranslationKey);
    expect($langJson[$uniqueTranslationKey])->toBe($uniqueTranslationKey);

    $filesystem->delete($bladeTestFile);
    $filesystem->deleteDirectory($bladeTestDir);
    $filesystem->delete($langTestFile);
});
