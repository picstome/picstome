<?php

namespace App\Services\Booking;

use Carbon\CarbonImmutable;

/**
 * A bookable start time, always held as UTC so it can be compared with the
 * database and rendered later in the photographer's timezone.
 */
final readonly class Slot
{
    public function __construct(
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
    ) {}
}
