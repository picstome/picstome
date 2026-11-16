<?php

use App\Exceptions\InsufficientStorageException;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Team;
use App\Models\User;
use App\Services\PlanEligibilityService;
use App\Services\StorageUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    StorageUsageService::flushRequestMemo();
});

afterEach(function () {
    StorageUsageService::flushRequestMemo();
});

function eligibilityTeam(int $usedBytes = 0): Team
{
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->personalTeam();

    if ($usedBytes > 0) {
        $gallery = Gallery::factory()->for($team)->create();
        Photo::factory()->for($gallery)->create(['stored_size' => $usedBytes]);
    }

    StorageUsageService::flushRequestMemo();

    return $team->fresh();
}

it('allows a plan that fits the used storage', function () {
    $team = eligibilityTeam(150 * 1024 ** 3);

    expect(app(PlanEligibilityService::class)->fits($team, '250gb'))->toBeTrue()
        ->and(app(PlanEligibilityService::class)->fits($team, '1tb'))->toBeTrue();
});

it('rejects a plan that does not fit the used storage', function () {
    $team = eligibilityTeam(150 * 1024 ** 3);

    expect(app(PlanEligibilityService::class)->fits($team, '100gb'))->toBeFalse();

    expect(fn () => app(PlanEligibilityService::class)->assertFits($team, '100gb'))
        ->toThrow(InsufficientStorageException::class, '150 GB');
});

it('allows any plan when the team stores nothing', function () {
    $team = eligibilityTeam();

    expect(app(PlanEligibilityService::class)->fits($team, '100gb'))->toBeTrue()
        ->and(app(PlanEligibilityService::class)->fits($team, '250gb'))->toBeTrue();
});
