<?php

use App\Enums\DepositType;
use App\Models\AvailabilityWindow;
use App\Models\BookingSetting;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;

    config([
        'picstome.bookings_feature_enabled' => true,
        'picstome.bookings_feature_admin_only' => true,
        'picstome.admin_emails' => [$this->user->email],
    ]);
});

it('opens the products tab from the query string', function () {
    Livewire::withQueryParams(['tab' => 'products'])
        ->actingAs($this->user)
        ->test('pages::bookings.settings')
        ->assertSet('tab', 'products')
        ->assertSeeLivewire('product-manager');
});

it('preloads a product from the query string', function () {
    $product = Product::factory()->for($this->team)->create(['name' => 'Wedding session']);

    Livewire::withQueryParams(['product' => $product->id])
        ->actingAs($this->user)
        ->test('product-manager')
        ->assertSet('productForm.product_id', $product->id)
        ->assertSet('productForm.name', 'Wedding session');
});

it('saves the reminder settings', function () {
    Livewire::actingAs($this->user)
        ->test('pages::bookings.settings')
        ->set('settingsForm.reminder_first_hours', '48')
        ->set('settingsForm.reminder_first_enabled', false)
        ->set('settingsForm.reminder_second_hours', '12')
        ->set('settingsForm.reminder_second_enabled', true)
        ->call('saveSettings')
        ->assertHasNoErrors();

    $settings = BookingSetting::forTeam($this->team);

    expect($settings->reminder_first_hours)->toBe(48)
        ->and($settings->reminder_first_enabled)->toBeFalse()
        ->and($settings->reminder_second_hours)->toBe(12)
        ->and($settings->reminder_second_enabled)->toBeTrue();
});

it('toggles a product active state', function () {
    $product = Product::factory()->for($this->team)->create(['is_active' => true]);

    Livewire::actingAs($this->user)
        ->test('product-manager')
        ->call('toggleProduct', $product->id);

    expect($product->fresh()->is_active)->toBeFalse();
});

it('reorders products when dragged', function () {
    $first = Product::factory()->for($this->team)->create(['name' => 'First', 'sort_order' => 0]);
    $second = Product::factory()->for($this->team)->create(['name' => 'Second', 'sort_order' => 1]);
    $third = Product::factory()->for($this->team)->create(['name' => 'Third', 'sort_order' => 2]);

    Livewire::actingAs($this->user)
        ->test('product-manager')
        ->call('reorderProducts', $third->id, $first->id);

    expect($third->fresh()->sort_order)->toBe(0)
        ->and($first->fresh()->sort_order)->toBe(1)
        ->and($second->fresh()->sort_order)->toBe(2);
});

it('moves a product to the bottom when dragged downwards', function () {
    $first = Product::factory()->for($this->team)->create(['name' => 'First', 'sort_order' => 0]);
    $second = Product::factory()->for($this->team)->create(['name' => 'Second', 'sort_order' => 1]);
    $third = Product::factory()->for($this->team)->create(['name' => 'Third', 'sort_order' => 2]);

    Livewire::actingAs($this->user)
        ->test('product-manager')
        ->call('reorderProducts', $first->id, $third->id);

    expect($second->fresh()->sort_order)->toBe(0)
        ->and($third->fresh()->sort_order)->toBe(1)
        ->and($first->fresh()->sort_order)->toBe(2);
});

it('blocks and unblocks a day from the monthly availability view', function () {
    Livewire::actingAs($this->user)
        ->test('pages::bookings.settings')
        ->set('scheduleView', 'monthly')
        ->call('toggleBlockedDay', '2026-06-05');

    expect($this->team->calendarBlocks()->count())->toBe(1);

    Livewire::actingAs($this->user)
        ->test('pages::bookings.settings')
        ->call('toggleBlockedDay', '2026-06-05');

    expect($this->team->calendarBlocks()->count())->toBe(0);
});

it('saves a product colour', function () {
    Livewire::actingAs($this->user)
        ->test('product-manager')
        ->call('openProductModal')
        ->set('productForm.name', 'Colour session')
        ->set('productForm.price', '100')
        ->set('productForm.color', 'coral')
        ->call('saveProduct')
        ->assertHasNoErrors();

    expect($this->team->products()->sole()->color)->toBe('coral');
});

it('is hidden from users who are not administrators', function () {
    config(['picstome.admin_emails' => []]);

    actingAs($this->user)->get('/bookings/settings')->assertRedirect(route('bookings.coming-soon'));
});

it('saves the weekly schedule', function () {
    Livewire::actingAs($this->user)
        ->test('pages::bookings.settings')
        ->set('settingsForm.windows.1.enabled', true)
        ->set('settingsForm.windows.1.start', '10:00')
        ->set('settingsForm.windows.1.end', '14:00')
        ->set('settingsForm.windows.6.enabled', false)
        ->set('settingsForm.windows.7.enabled', false)
        ->call('saveSettings')
        ->assertHasNoErrors();

    $monday = AvailabilityWindow::query()
        ->where('team_id', $this->team->id)
        ->where('weekday', 1)
        ->sole();

    expect(substr($monday->start_time, 0, 5))->toBe('10:00')
        ->and(substr($monday->end_time, 0, 5))->toBe('14:00')
        ->and(AvailabilityWindow::query()->where('team_id', $this->team->id)->where('weekday', 7)->exists())->toBeFalse();
});

it('copies Monday to every day', function () {
    Livewire::actingAs($this->user)
        ->test('pages::bookings.settings')
        ->set('settingsForm.windows.1.enabled', true)
        ->set('settingsForm.windows.1.start', '08:00')
        ->set('settingsForm.windows.1.end', '12:00')
        ->call('copyMondayToAll')
        ->assertSet('settingsForm.windows.7.start', '08:00')
        ->assertSet('settingsForm.windows.7.enabled', true);
});

it('rejects a window whose end is before its start', function () {
    Livewire::actingAs($this->user)
        ->test('pages::bookings.settings')
        ->set('settingsForm.windows.1.enabled', true)
        ->set('settingsForm.windows.1.start', '14:00')
        ->set('settingsForm.windows.1.end', '09:00')
        ->call('saveSettings')
        ->assertHasErrors('settingsForm.windows.1.end');
});

it('stores the default deposit in cents when entered in euros', function () {
    Livewire::actingAs($this->user)
        ->test('pages::bookings.settings')
        ->set('settingsForm.default_deposit_type', 'fixed')
        ->set('settingsForm.default_deposit_value', '60,00')
        ->call('saveSettings')
        ->assertHasNoErrors()
        ->assertSet('settingsForm.default_deposit_value', '60.00');

    expect(BookingSetting::forTeam($this->team)->default_deposit_value)->toBe(6000);
});

it('stores the default deposit percentage as a whole number', function () {
    Livewire::actingAs($this->user)
        ->test('pages::bookings.settings')
        ->set('settingsForm.default_deposit_type', 'percent')
        ->set('settingsForm.default_deposit_value', '30')
        ->call('saveSettings')
        ->assertHasNoErrors();

    $settings = BookingSetting::forTeam($this->team);

    expect($settings->default_deposit_type)->toBe(DepositType::Percent)
        ->and($settings->default_deposit_value)->toBe(30);
});

it('saves the booking rules', function () {
    Livewire::actingAs($this->user)
        ->test('pages::bookings.settings')
        ->set('settingsForm.buffer_minutes', '20')
        ->set('settingsForm.min_notice_hours', '48')
        ->set('settingsForm.horizon_days', '60')
        ->set('settingsForm.max_sessions_per_day', '3')
        ->call('saveSettings')
        ->assertHasNoErrors();

    $settings = BookingSetting::forTeam($this->team);

    expect($settings->buffer_minutes)->toBe(20)
        ->and($settings->min_notice_hours)->toBe(48)
        ->and($settings->horizon_days)->toBe(60)
        ->and($settings->max_sessions_per_day)->toBe(3);
});

it('saves the cancellation policy and the Bizum details', function () {
    Livewire::actingAs($this->user)
        ->test('pages::bookings.settings')
        ->set('settingsForm.cancellation_policy', 'The deposit is not refundable.')
        ->set('settingsForm.bizum_enabled', true)
        ->set('settingsForm.bizum_phone', '+34 600 000 000')
        ->call('saveSettings')
        ->assertHasNoErrors();

    $settings = BookingSetting::forTeam($this->team);

    expect($settings->cancellation_policy)->toBe('The deposit is not refundable.')
        ->and($settings->bizum_enabled)->toBeTrue()
        ->and($settings->bizum_phone)->toBe('+34 600 000 000');
});

it('saves the payment methods and the required phone', function () {
    Livewire::actingAs($this->user)
        ->test('pages::bookings.settings')
        ->set('settingsForm.card_enabled', false)
        ->set('settingsForm.bizum_enabled', true)
        ->set('settingsForm.bizum_phone', '+34 600 000 000')
        ->set('settingsForm.phone_required', true)
        ->set('settingsForm.auto_issue_invoices', true)
        ->call('saveSettings')
        ->assertHasNoErrors();

    $settings = BookingSetting::forTeam($this->team);

    expect($settings->card_enabled)->toBeFalse()
        ->and($settings->bizum_enabled)->toBeTrue()
        ->and($settings->phone_required)->toBeTrue()
        ->and($settings->auto_issue_invoices)->toBeTrue();
});

it('rejects disabling every payment method', function () {
    Livewire::actingAs($this->user)
        ->test('pages::bookings.settings')
        ->set('settingsForm.card_enabled', false)
        ->set('settingsForm.bizum_enabled', false)
        ->call('saveSettings')
        ->assertHasErrors('settingsForm.card_enabled');
});

it('resolves the coordinates of the product location', function () {
    Cache::flush();

    Http::fake([
        'photon.komoot.io/*' => Http::response([
            'features' => [[
                'geometry' => ['coordinates' => [-0.8891, 41.6488]],
                'properties' => ['name' => 'Plaza del Pilar', 'city' => 'Zaragoza'],
            ]],
        ]),
    ]);

    Livewire::actingAs($this->user)
        ->test('product-manager')
        ->call('openProductModal')
        ->set('productForm.name', 'Sunset session')
        ->set('productForm.price', '80')
        ->set('productForm.location_name', 'Plaza del Pilar')
        ->call('searchLocation')
        ->call('selectLocation', 0)
        ->call('saveProduct')
        ->assertHasNoErrors();

    $product = $this->team->products()->sole();

    expect($product->latitude)->toBe(41.6488)
        ->and($product->longitude)->toBe(-0.8891);
});

it('creates a product with a fixed deposit', function () {
    Livewire::actingAs($this->user)
        ->test('product-manager')
        ->call('openProductModal')
        ->set('productForm.name', 'Wedding session')
        ->set('productForm.duration_minutes', '120')
        ->set('productForm.price', '250,50')
        ->set('productForm.deposit_type', 'fixed')
        ->set('productForm.deposit_value', '50,00')
        ->call('saveProduct')
        ->assertHasNoErrors();

    $product = $this->team->products()->sole();

    expect($product->name)->toBe('Wedding session')
        ->and($product->duration_minutes)->toBe(120)
        ->and($product->price)->toBe(25050)
        ->and($product->deposit_type)->toBe(DepositType::Fixed)
        ->and($product->deposit_value)->toBe(5000);
});

it('edits and deletes a product', function () {
    $product = Product::factory()->for($this->team)->create(['price' => 10000]);

    Livewire::actingAs($this->user)
        ->test('product-manager')
        ->call('openProductModal', $product->id)
        ->set('productForm.name', 'Updated name')
        ->call('saveProduct')
        ->assertHasNoErrors();

    expect($product->fresh()->name)->toBe('Updated name');

    Livewire::actingAs($this->user)
        ->test('product-manager')
        ->call('requestDeleteProduct', $product->id)
        ->call('deleteProduct');

    expect($this->team->products()->count())->toBe(0);
});
