<?php

use App\Models\Booking;
use App\Models\Product;
use App\Models\Team;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingPaid;
use App\Services\Booking\BookingCalendarLinks;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->team = Team::factory()->create(['handle' => 'estudio']);

    $this->product = Product::factory()->for($this->team)->create([
        'name' => 'Sesión al atardecer',
        'location_name' => 'Playa de la Malvarrosa',
    ]);

    $this->booking = Booking::factory()->for($this->team)->forProduct($this->product)->create([
        'starts_at' => CarbonImmutable::parse('2026-06-01 09:00', 'Europe/Madrid'),
        'ends_at' => CarbonImmutable::parse('2026-06-01 10:00', 'Europe/Madrid'),
    ]);
});

it('builds a Google Calendar link with the event in UTC', function () {
    $url = urldecode(app(BookingCalendarLinks::class)->google($this->booking));

    expect($url)->toContain('calendar.google.com')
        ->and($url)->toContain('action=TEMPLATE')
        ->and($url)->toContain('dates=20260601T070000Z/20260601T080000Z')
        ->and($url)->toContain('Sesión al atardecer')
        ->and($url)->toContain('Playa de la Malvarrosa');
});

it('builds an Outlook Calendar link', function () {
    $url = urldecode(app(BookingCalendarLinks::class)->outlook($this->booking));

    expect($url)->toContain('outlook.live.com')
        ->and($url)->toContain('rru=addevent')
        ->and($url)->toContain('startdt=2026-06-01T07:00:00Z')
        ->and($url)->toContain('enddt=2026-06-01T08:00:00Z');
});

it('uses the .ics file as the iCloud link', function () {
    $url = app(BookingCalendarLinks::class)->ics($this->booking);

    expect($url)->toContain('/@estudio/book/'.$this->booking->ulid.'/calendar.ics');
});

it('includes the three calendar links in the client email', function () {
    $mail = (new BookingConfirmed($this->booking))->toMail($this->team->owner);
    $body = implode(' ', $mail->introLines);

    expect($body)->toContain('calendar.google.com')
        ->and($body)->toContain('outlook.live.com')
        ->and($body)->toContain('calendar.ics');
});

it('includes the three calendar links in the photographer email', function () {
    $mail = (new BookingPaid($this->booking))->toMail($this->team->owner);
    $body = implode(' ', $mail->introLines);

    expect($body)->toContain('calendar.google.com')
        ->and($body)->toContain('outlook.live.com')
        ->and($body)->toContain('calendar.ics');
});
