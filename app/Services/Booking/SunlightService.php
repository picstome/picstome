<?php

namespace App\Services\Booking;

use App\Enums\LightMode;
use Carbon\CarbonImmutable;

/**
 * Sunrise and sunset of a place on a given day.
 *
 * Uses PHP's built-in `date_sun_info`, so it needs no dependency and no API
 * key. It is a recommendation only: it never hides a free slot and it is not
 * a weather forecast.
 */
class SunlightService
{
    /**
     * Local sunset (or sunrise) of the day.
     */
    public function sunTime(
        CarbonImmutable $date,
        float $latitude,
        float $longitude,
        string $timezone,
        LightMode $mode,
    ): ?CarbonImmutable {
        $noon = $date->setTimezone($timezone)->setTime(12, 0);
        $info = date_sun_info($noon->timestamp, $latitude, $longitude);

        $timestamp = $mode === LightMode::Sunrise ? $info['sunrise'] : $info['sunset'];

        if ($timestamp === false) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp($timestamp, $timezone);
    }

    /**
     * Start of the slot that best matches the light.
     *
     * For a sunset session the ideal start is the one that ends at sunset;
     * for a sunrise session it is the sunrise itself. If no slot is within
     * 90 minutes of that moment, nothing is highlighted.
     *
     * @param  array<int, Slot>  $slots
     */
    public function recommendedStart(
        array $slots,
        LightMode $mode,
        CarbonImmutable $date,
        float $latitude,
        float $longitude,
        string $timezone,
        int $durationMinutes,
    ): ?CarbonImmutable {
        if ($mode === LightMode::None || $slots === []) {
            return null;
        }

        $sunTime = $this->sunTime($date, $latitude, $longitude, $timezone, $mode);

        if ($sunTime === null) {
            return null;
        }

        $ideal = $mode === LightMode::Sunset
            ? $sunTime->subMinutes($durationMinutes)
            : $sunTime;

        $best = null;
        $bestDistance = null;

        foreach ($slots as $slot) {
            $distance = abs((float) $slot->startsAt->diffInSeconds($ideal));

            if ($bestDistance === null || $distance < $bestDistance) {
                $best = $slot->startsAt;
                $bestDistance = $distance;
            }
        }

        if ($best === null) {
            return null;
        }

        if ($bestDistance > 90 * 60) {
            return null;
        }

        return $best;
    }
}
