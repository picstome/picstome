<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Team;
use App\Services\Booking\BookingCalendarFile;

/**
 * Public .ics download of a booked session.
 *
 * The ulid in the URL is unguessable, so the link works in any email client
 * without adding another token to the booking.
 */
class BookingCalendarController extends Controller
{
    public function __invoke(string $handle, Booking $booking)
    {
        $team = Team::where('handle', strtolower($handle))->firstOrFail();

        abort_unless($booking->team_id === $team->id, 404);

        return response(app(BookingCalendarFile::class)->for($booking), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="session.ics"',
        ]);
    }
}
