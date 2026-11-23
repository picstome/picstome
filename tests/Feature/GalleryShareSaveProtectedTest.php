<?php

use App\Models\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a shared gallery is save protected when downloads are off or a watermark is shown', function (bool $watermarked, bool $hasWatermark, bool $downloadable, bool $protected) {
    $gallery = Gallery::factory()->shared()->create([
        'is_share_watermarked' => $watermarked,
        'is_share_downloadable' => $downloadable,
    ]);
    $gallery->team->update([
        'brand_watermark_type' => 'text',
        'brand_watermark_text' => $hasWatermark ? 'Acme' : null,
    ]);

    expect($gallery->fresh()->isShareSaveProtected())->toBe($protected);
})->with([
    'downloads off' => [false, false, false, true],
    'watermarked' => [true, true, true, true],
    'watermark enabled but team has none' => [true, false, true, false],
    'downloadable, no watermark' => [false, true, true, false],
]);
