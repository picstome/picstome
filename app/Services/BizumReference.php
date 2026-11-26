<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Short, easy-to-type reference for a Bizum transfer.
 *
 * "Bea-27" is the first name plus the day of the session. If it is already
 * taken, a numeric suffix is added ("Bea-27-2"). The same format is used by
 * bookings and payment links so clients only have to copy one simple word.
 */
class BizumReference
{
    public function forBooking(Booking $booking): string
    {
        return $this->unique(
            $this->base($booking->customer_name, $booking->starts_at->setTimezone($booking->timezone)),
            fn (string $candidate): bool => Booking::query()
                ->where('team_id', $booking->team_id)
                ->where('bizum_reference', $candidate)
                ->whereKeyNot($booking->id)
                ->exists(),
        );
    }

    public function forPayment(Team $team, string $customerName, CarbonInterface $date): string
    {
        return $this->unique(
            $this->base($customerName, $date),
            fn (string $candidate): bool => Payment::query()->where('reference', $candidate)->exists(),
        );
    }

    private function base(string $name, CarbonInterface $date): string
    {
        $first = Str::of($name)
            ->before('@')
            ->trim()
            ->explode(' ')
            ->first();

        $first = preg_replace('/[^A-Za-z0-9]/', '', Str::ascii((string) $first)) ?: 'Cliente';
        $first = Str::limit(Str::ucfirst(Str::lower($first)), 14, '');

        return $first.'-'.$date->day;
    }

    /**
     * @param  callable(string): bool  $exists
     */
    private function unique(string $base, callable $exists): string
    {
        $candidate = $base;
        $suffix = 2;

        while ($exists($candidate)) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
