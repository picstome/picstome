<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Team}
 */
function storageBannerFixture(int $limit = 1000, int $used = 950): array
{
    $owner = User::factory()->withPersonalTeam()->create();
    $team = $owner->personalTeam();

    $team->update(['custom_storage_limit' => $limit]);

    Photo::factory()
        ->for(Gallery::factory()->for($team))
        ->create([
            'size' => $used,
            'stored_size' => $used,
        ]);

    return [$owner, $team];
}

it('shows the banner when storage reaches the threshold', function () {
    [$owner] = storageBannerFixture();

    Livewire::actingAs($owner)
        ->test('storage-alert-banner')
        ->assertSee('Your storage is almost full');
});

it('does not show the banner below the threshold', function () {
    [$owner] = storageBannerFixture(1000, 900);

    Livewire::actingAs($owner)
        ->test('storage-alert-banner')
        ->assertDontSee('Your storage is almost full');
});

it('does not show the banner for unlimited storage', function () {
    [$owner, $team] = storageBannerFixture();

    $team->update(['custom_storage_limit' => null]);

    Livewire::actingAs($owner)
        ->test('storage-alert-banner')
        ->assertDontSee('Your storage is almost full');
});

it('dismisses the banner until the plan changes', function () {
    [$owner, $team] = storageBannerFixture();

    Livewire::actingAs($owner)
        ->test('storage-alert-banner')
        ->assertSee('Your storage is almost full')
        ->call('dismiss')
        ->assertDontSee('Your storage is almost full');

    expect($team->fresh()->storage_alert_dismissed_at)->not->toBeNull();

    Livewire::actingAs($owner)
        ->test('storage-alert-banner')
        ->assertDontSee('Your storage is almost full');

    $team->update(['custom_storage_limit' => 2000]);
    $team->update(['custom_storage_limit' => 1000]);

    $owner->unsetRelation('currentTeam');

    Livewire::actingAs($owner)
        ->test('storage-alert-banner')
        ->assertSee('Your storage is almost full');
});

it('shows and dismisses the banner on the dashboard', function () {
    [$owner] = storageBannerFixture();

    $this->actingAs($owner);

    visit('/dashboard')
        ->assertSee('Your storage is almost full')
        ->click('[data-test="storage-alert-dismiss"]')
        ->assertDontSee('Your storage is almost full')
        ->assertNoJavascriptErrors();
});
