<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-01 05:00:00', 'UTC'));

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;

    config([
        'picstome.bookings_feature_enabled' => true,
        'picstome.bookings_feature_admin_only' => true,
        'picstome.admin_emails' => [$this->user->email],
    ]);

    $product = Product::factory()->for($this->team)->create(['name' => 'Sunset session']);

    Booking::factory()->for($this->team)->pending()->forProduct($product)->create([
        'starts_at' => CarbonImmutable::parse('2026-06-03 09:00', 'Europe/Madrid'),
        'ends_at' => CarbonImmutable::parse('2026-06-03 10:00', 'Europe/Madrid'),
        'customer_name' => 'Ana Torres',
    ]);
});

it('shows only unpaid bookings on the dashboard for administrators', function () {
    $confirmed = Booking::factory()->for($this->team)->create([
        'status' => BookingStatus::Confirmed,
        'product_name' => 'Already paid session',
        'customer_name' => 'Luis Ramos',
        'starts_at' => CarbonImmutable::parse('2026-06-04 09:00', 'Europe/Madrid'),
        'ends_at' => CarbonImmutable::parse('2026-06-04 10:00', 'Europe/Madrid'),
    ]);

    actingAs($this->user)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertSee('Pending payments')
        ->assertSee('Sunset session')
        ->assertSee('Ana Torres')
        ->assertDontSee('Already paid session')
        ->assertDontSee('Luis Ramos');
});

it('hides the pending payments reminder from users without access', function () {
    config(['picstome.admin_emails' => []]);

    actingAs($this->user)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertDontSee('Pending payments');
});
