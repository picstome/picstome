<?php

use App\Enums\DepositType;
use App\Models\BookingSetting;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-01 05:00:00', 'UTC'));

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;

    config([
        'picstome.bookings_feature_enabled' => true,
        'picstome.bookings_feature_admin_only' => true,
        'picstome.admin_emails' => [$this->user->email],
        'picstome.payments_fake' => true,
        'app.debug' => true,
    ]);

    BookingSetting::forTeam($this->team)->update([
        'timezone' => 'Europe/Madrid',
        'card_enabled' => true,
    ]);

    $this->product = Product::factory()->for($this->team)->create([
        'name' => 'Sunset session',
        'description' => 'A session on the beach',
        'duration_minutes' => 60,
        'price' => 20000,
        'deposit_type' => DepositType::Fixed,
        'deposit_value' => 5000,
    ]);
});

it('lists the bookable products with a link to the calendar', function () {
    get('/@'.$this->team->handle.'/products')
        ->assertSuccessful()
        ->assertSee('Sunset session')
        ->assertSee('A session on the beach')
        ->assertSee('60')
        ->assertSee(route('handle.book', [
            'handle' => $this->team->handle,
            'productUlid' => $this->product->ulid,
        ]), false);
});

it('hides inactive products', function () {
    $this->product->update(['is_active' => false]);

    Product::factory()->for($this->team)->create(['name' => 'Active session']);

    get('/@'.$this->team->handle.'/products')
        ->assertSuccessful()
        ->assertSee('Active session')
        ->assertDontSee('Sunset session');
});

it('is not available when the feature is disabled', function () {
    config(['picstome.bookings_feature_enabled' => false]);

    get('/@'.$this->team->handle.'/products')->assertNotFound();
});

it('is not available for a team whose owner cannot see the feature', function () {
    config(['picstome.admin_emails' => []]);

    get('/@'.$this->team->handle.'/products')->assertNotFound();
});
