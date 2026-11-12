<?php

use App\Models\PhotoComment;

it('formats a timestamp as minutes and seconds', function (float $seconds, string $expected) {
    expect(PhotoComment::formatTimecode($seconds))->toBe($expected);
})->with([
    'zero' => [0.0, '00:00'],
    'seconds' => [42.37, '00:42'],
    'minute' => [60.0, '01:00'],
    'just under an hour' => [3599.0, '59:59'],
    'hour' => [3600.0, '1:00:00'],
    'hour and change' => [3725.9, '1:02:05'],
]);

it('rounds a timestamp down so the second shown is the second played', function () {
    expect(PhotoComment::formatTimecode(9.99))->toBe('00:09');
});

it('reports whether a comment is anchored to a second', function () {
    $plain = new PhotoComment(['comment' => 'hello']);
    $timed = new PhotoComment(['comment' => 'hello', 'time_seconds' => 12.5]);
    $pinned = new PhotoComment(['comment' => 'hello', 'x' => 10, 'y' => 20]);

    expect($plain->hasTimestamp())->toBeFalse()
        ->and($plain->isAnchored())->toBeFalse()
        ->and($plain->timecode())->toBeNull()
        ->and($timed->hasTimestamp())->toBeTrue()
        ->and($timed->isAnchored())->toBeTrue()
        ->and($timed->hasCoordinates())->toBeFalse()
        ->and($timed->timecode())->toBe('00:12')
        ->and($pinned->isAnchored())->toBeTrue()
        ->and($pinned->hasTimestamp())->toBeFalse();
});
