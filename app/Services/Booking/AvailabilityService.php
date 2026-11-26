<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingSetting;
use App\Models\CalendarBlock;
use App\Models\Product;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds the bookable starts of a day from the rules, never from a stored
 * list of slots.
 *
 * Rules applied, in order:
 *  - the date must be inside the booking horizon and not in the past;
 *  - the weekday must have an availability window;
 *  - the day must not already have reached its session cap;
 *  - a session (plus the product buffer) must fit before the window closes;
 *  - the start must respect the minimum notice;
 *  - the range must not overlap a calendar block or a blocking booking;
 *  - starts advance in `slot_step_minutes` steps.
 *
 * Buffers are applied by expanding the existing bookings on both sides. That
 * keeps the gap between sessions without needing a trailing buffer before
 * closing time.
 */
class AvailabilityService
{
    /**
     * @return Collection<int, Slot>
     */
    public function slotsForDate(Team $team, Product $product, CarbonImmutable $date): Collection
    {
        $settings = $team->bookingSetting ?? BookingSetting::forTeam($team);
        $timezone = $settings->timezone;

        $dayStartsAt = $date->setTimezone($timezone)->startOfDay();
        $dayEndsAt = $dayStartsAt->addDay();
        $now = CarbonImmutable::now($timezone);

        if ($dayStartsAt->lessThan($now->startOfDay())) {
            return collect();
        }

        if ($dayStartsAt->greaterThan($now->startOfDay()->addDays($settings->horizon_days))) {
            return collect();
        }

        $windows = $team->availabilityWindows()
            ->where('weekday', $dayStartsAt->dayOfWeekIso)
            ->orderBy('start_time')
            ->get();

        if ($windows->isEmpty()) {
            return collect();
        }

        $bookings = $team->bookings()
            ->blocking()
            ->overlapping($dayStartsAt->utc(), $dayEndsAt->utc())
            ->get();

        if ($this->reachedDailyCap($team, $settings, $dayStartsAt->utc(), $dayEndsAt->utc())) {
            return collect();
        }

        $blocks = $team->calendarBlocks()
            ->overlapping($dayStartsAt->utc(), $dayEndsAt->utc())
            ->get();

        $duration = $product->duration_minutes;
        $buffer = $product->effectiveBufferMinutes($settings);
        $step = max(5, $settings->slot_step_minutes);
        $earliest = $now->addHours($settings->min_notice_hours);

        $slots = collect();
        $seenLabels = [];

        foreach ($windows as $window) {
            $windowStart = $this->localTime($dayStartsAt, $window->start_time);
            $windowEnd = $this->localTime($dayStartsAt, $window->end_time);

            // A window that falls in the skipped hour of the spring-forward
            // night does not exist; normalising it would duplicate a slot.
            if ($windowStart->format('H:i') !== $this->hourAndMinute($window->start_time)) {
                continue;
            }

            if ($windowEnd->format('H:i') !== $this->hourAndMinute($window->end_time)) {
                continue;
            }

            if ($windowEnd->lessThanOrEqualTo($windowStart)) {
                continue;
            }

            for (
                $cursor = $windowStart;
                $cursor->addMinutes($duration)->lessThanOrEqualTo($windowEnd);
                $cursor = $cursor->addMinutes($step)
            ) {
                $slotEndsAt = $cursor->addMinutes($duration);

                if ($cursor->lessThan($earliest)) {
                    continue;
                }

                if ($this->overlapsBlock($cursor, $slotEndsAt, $blocks)) {
                    continue;
                }

                if ($this->overlapsBooking($cursor, $slotEndsAt, $bookings, $buffer)) {
                    continue;
                }

                // On the fall-back night the same wall time exists twice.
                // Clients should see a single "02:00", so the second instant
                // is skipped once that label was already offered.
                $label = $cursor->format('H:i');

                if (isset($seenLabels[$label])) {
                    continue;
                }

                $seenLabels[$label] = true;

                $slots->push(new Slot($cursor->utc(), $slotEndsAt->utc()));
            }
        }

        return $slots->values();
    }

    /**
     * Whether a specific start can be reserved right now.
     */
    public function isAvailable(Team $team, Product $product, CarbonImmutable $startsAt): bool
    {
        return $this->slotsForDate($team, $product, $startsAt)->contains(
            fn (Slot $slot) => $slot->startsAt->equalTo($startsAt->utc()),
        );
    }

    /**
     * Resolve a stored "HH:MM:SS" window time on a local day.
     */
    private function localTime(CarbonImmutable $day, string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($day->format('Y-m-d').' '.$time, $day->getTimezone());
    }

    private function hourAndMinute(string $time): string
    {
        return substr($time, 0, 5);
    }

    private function reachedDailyCap(
        Team $team,
        BookingSetting $settings,
        CarbonImmutable $dayStartsAt,
        CarbonImmutable $dayEndsAt,
    ): bool {
        if ($settings->max_sessions_per_day === null) {
            return false;
        }

        $sessions = $team->bookings()
            ->blocking()
            ->where('starts_at', '>=', $dayStartsAt)
            ->where('starts_at', '<', $dayEndsAt)
            ->count();

        return $sessions >= $settings->max_sessions_per_day;
    }

    /**
     * @param  Collection<int, CalendarBlock>  $blocks
     */
    private function overlapsBlock(
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        Collection $blocks,
    ): bool {
        foreach ($blocks as $block) {
            if ($startsAt->lessThan($block->ends_at) && $endsAt->greaterThan($block->starts_at)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, Booking>  $bookings
     */
    private function overlapsBooking(
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        Collection $bookings,
        int $buffer,
    ): bool {
        foreach ($bookings as $booking) {
            $occupiedStart = $booking->starts_at->subMinutes($buffer);
            $occupiedEnd = $booking->ends_at->addMinutes($buffer);

            if ($startsAt->lessThan($occupiedEnd) && $endsAt->greaterThan($occupiedStart)) {
                return true;
            }
        }

        return false;
    }
}
