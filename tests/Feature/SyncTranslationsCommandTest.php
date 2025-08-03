<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Filesystem\Filesystem;

it('adds missing translation keys to lang files', function () {
    $fs = new Filesystem();
    $testBladeDir = resource_path('views/test_translations');
    $testBladeFile = $testBladeDir . '/test.blade.php';
    $testLangDir = base_path('lang');
    $testLangFile = $testLangDir . '/test.json';
    $uniqueKey = 'Unique Test Key ' . uniqid();

    // Setup: create a test blade file with a unique translation key
    $fs->ensureDirectoryExists($testBladeDir);
    $fs->put($testBladeFile, "{{ __('$uniqueKey') }}");

    // Setup: create a test lang file missing the key
    $fs->put($testLangFile, json_encode(["Existing Key" => "Existing Value"], JSON_PRETTY_PRINT));

    // Run the command
    Artisan::call('translations:sync');

    // Assert the key was added
    $json = json_decode($fs->get($testLangFile), true);
    expect($json)->toHaveKey($uniqueKey);
    expect($json[$uniqueKey])->toBe($uniqueKey);

    // Cleanup
    $fs->delete($testBladeFile);
    $fs->deleteDirectory($testBladeDir);
    $fs->delete($testLangFile);
});
