<?php

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Issuing an invoice writes its PDF to the configured disk.
    Storage::fake(config('picstome.disk'));
});

it('requires authentication', function () {
    $this->get('/finance')->assertRedirect(route('login'));
});

it('shows revenue, invoice and booking figures', function () {
    config([
        'picstome.bookings_feature_enabled' => true,
        'picstome.bookings_feature_admin_only' => false,
    ]);

    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    Payment::factory()->for($team)->create([
        'amount' => 10000,
        'completed_at' => now()->subDays(2),
    ]);

    Payment::factory()->for($team)->create([
        'amount' => 20000,
        'completed_at' => now()->subDays(45),
    ]);

    $unpaid = draftInvoice($team, ['total' => 121]);
    $unpaid->forceFill([
        'status' => Invoice::STATUS_ISSUED,
        'full_number' => 'INV-2026-0001',
        'due_date' => now()->subDay(),
    ])->save();

    draftInvoice($team, ['total' => 50]);

    Booking::factory()->for($team)->pending()->create([
        'product_name' => 'Family session',
        'customer_name' => 'Ana',
        'total_amount' => 15000,
    ]);

    Livewire::actingAs($user)
        ->test('pages::finance')
        ->assertSee('Finance')
        ->assertSee('€100.00')
        ->assertSee('50.0% less than the previous 30 days')
        ->assertSee('Unpaid invoices')
        ->assertSee('INV-2026-0001')
        ->assertSee('Overdue')
        ->assertSee('Draft invoices')
        ->assertSee('Pending bookings')
        ->assertSee('Family session')
        ->assertSeeHtml('btn-ink')
        ->assertSeeHtml('btn-coral');
});

it('hides the booking block when the feature is admin-only', function () {
    config([
        'picstome.bookings_feature_enabled' => true,
        'picstome.bookings_feature_admin_only' => true,
        'picstome.admin_emails' => ['admin@example.com'],
    ]);

    $user = User::factory()->withPersonalTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::finance')
        ->assertSee('Coming soon')
        ->assertSee(route('bookings.coming-soon'))
        ->assertDontSee('Pending bookings')
        ->assertDontSee('Open the agenda');
});

it('links the dashboard revenue card to the finance page', function () {
    $user = User::factory()->withPersonalTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee(route('finance'));
});
