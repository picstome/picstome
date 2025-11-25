<?php

use App\Services\RawPhotoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

it('can check if exiftool is available', function () {
    $service = new RawPhotoService;

    // Mock Process facade to simulate ExifTool availability
    $mockResult = Mockery::mock();
    $mockResult->shouldReceive('successful')->andReturn(true);

    Process::shouldReceive('run')
        ->with('which exiftool')
        ->andReturn($mockResult);

    expect($service->isExifToolAvailable())->toBeTrue();
});

it('returns false when exiftool is not available', function () {
    $service = new RawPhotoService;

    // Mock Process facade to simulate ExifTool unavailability
    $mockResult = Mockery::mock();
    $mockResult->shouldReceive('successful')->andReturn(false);

    Process::shouldReceive('run')
        ->with('which exiftool')
        ->andReturn($mockResult);

    expect($service->isExifToolAvailable())->toBeFalse();
});

it('can identify raw file extensions', function () {
    $service = new RawPhotoService;

    expect($service->isRawFile('photo.cr2'))->toBeTrue();
    expect($service->isRawFile('photo.nef'))->toBeTrue();
    expect($service->isRawFile('photo.arw'))->toBeTrue();
    expect($service->isRawFile('photo.dng'))->toBeTrue();
    expect($service->isRawFile('photo.jpg'))->toBeFalse();
    expect($service->isRawFile('photo.png'))->toBeFalse();
});

it('returns all supported raw extensions', function () {
    $service = new RawPhotoService;
    $extensions = $service->getSupportedExtensions();

    expect($extensions)->toBeArray();
    expect($extensions)->toContain('cr2', 'nef', 'arw', 'dng', 'orf', 'rw2', 'pef', 'srw', 'mos', 'mrw', '3fr');
});
