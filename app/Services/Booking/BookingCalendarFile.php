<?php

namespace App\Services\Booking;

use App\Models\Booking;

/**
 * Builds the .ics file of a booking.
 *
 * An attachment works in every calendar (Google, Apple, Outlook) and removes
 * the provider-specific links from the critical path of the confirmation.
 */
class BookingCalendarFile
{
    public function for(Booking $booking): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Picstome//Bookings//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$booking->ulid.'@picstome.com',
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$booking->starts_at->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$booking->ends_at->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape($booking->product_name),
            'DESCRIPTION:'.$this->escape($this->description($booking)),
            'LOCATION:'.$this->escape((string) $booking->product?->location_name),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines)."\r\n";
    }

    private function description(Booking $booking): string
    {
        return __('Session with :name', ['name' => $booking->customer_name]);
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\n", "\r"],
            ['\\\\', '\;', '\,', '\n', ''],
            $value,
        );
    }
}
