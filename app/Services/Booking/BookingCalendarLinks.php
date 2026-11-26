<?php

namespace App\Services\Booking;

use App\Models\Booking;

/**
 * "Add to calendar" links for the confirmation email.
 *
 * Google and Outlook accept a pre-filled event through a public URL. iCloud
 * has no such page, so the iCloud button points to the .ics file: on iPhone,
 * iPad and macOS the system opens it directly in Calendar.
 */
class BookingCalendarLinks
{
    public function google(Booking $booking): string
    {
        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => $booking->product_name,
            'dates' => $booking->starts_at->utc()->format('Ymd\THis\Z')
                .'/'.$booking->ends_at->utc()->format('Ymd\THis\Z'),
            'details' => $this->description($booking),
            'location' => (string) $booking->product?->location_name,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function outlook(Booking $booking): string
    {
        return 'https://outlook.live.com/calendar/0/deeplink/compose?'.http_build_query([
            'path' => '/calendar/action/compose',
            'rru' => 'addevent',
            'subject' => $booking->product_name,
            'startdt' => $booking->starts_at->utc()->format('Y-m-d\TH:i:s\Z'),
            'enddt' => $booking->ends_at->utc()->format('Y-m-d\TH:i:s\Z'),
            'body' => $this->description($booking),
            'location' => (string) $booking->product?->location_name,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Universal fallback: the .ics file opens in Apple Calendar, Google
     * Calendar, Outlook and any other client.
     */
    public function ics(Booking $booking): string
    {
        return route('handle.book.ics', [
            'handle' => $booking->team->handle,
            'booking' => $booking->ulid,
        ]);
    }

    private function description(Booking $booking): string
    {
        return __('Session with :name', ['name' => $booking->customer_name]);
    }
}
