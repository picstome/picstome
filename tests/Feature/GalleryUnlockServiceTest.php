<?php

use App\Models\Customer;
use App\Models\Gallery;
use App\Models\Photoshoot;
use App\Services\GalleryUnlockService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('marks each matching gallery ulid once', function () {
    $customer = Customer::factory()->create();
    $photoshoot = Photoshoot::factory()->for($customer)->create();
    $galleryA = Gallery::factory()->shared()->protected(password: 'secret')->for($photoshoot)->create();
    $galleryB = Gallery::factory()->shared()->protected(password: 'secret')->for($photoshoot)->create();

    $service = app(GalleryUnlockService::class);

    expect($service->unlockCustomerGalleries($customer, 'secret'))->toBe(2);
    expect($service->unlockedUlids())->toHaveCount(2)->toContain((string) $galleryA->ulid, (string) $galleryB->ulid);

    expect($service->unlockCustomerGalleries($customer, 'secret'))->toBe(0);
    expect($service->unlockedUlids())->toHaveCount(2);
});

it('writes nothing to the session when no gallery matches the password', function () {
    $customer = Customer::factory()->create();
    $photoshoot = Photoshoot::factory()->for($customer)->create();
    Gallery::factory()->shared()->protected(password: 'secret')->for($photoshoot)->create();

    $service = app(GalleryUnlockService::class);

    expect($service->unlockCustomerGalleries($customer, 'wrong-password'))->toBe(0);
    expect($service->unlockedUlids())->toBeEmpty();
});

it('returns false when the seed password does not verify', function () {
    $photoshoot = Photoshoot::factory()->create();
    $gallery = Gallery::factory()->shared()->protected(password: 'secret')->for($photoshoot)->create();

    $service = app(GalleryUnlockService::class);

    expect($service->unlock($gallery, 'wrong-password'))->toBeFalse();
    expect($service->unlockedUlids())->toBeEmpty();
});

it('scopes a standalone seed to itself', function () {
    $gallery = Gallery::factory()->shared()->protected(password: 'secret')->create();
    $otherGallery = Gallery::factory()->shared()->protected(password: 'secret')->create();

    $service = app(GalleryUnlockService::class);

    expect($service->unlock($gallery, 'secret'))->toBeTrue();
    expect($service->unlockedUlids())->toBe([(string) $gallery->ulid]);
    expect($service->unlockedUlids())->not->toContain((string) $otherGallery->ulid);
});
