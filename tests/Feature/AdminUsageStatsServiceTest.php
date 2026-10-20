<?php

use App\Models\Contract;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Team;
use App\Models\User;
use App\Services\AdminUsageStatsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Carbon::setTestNow('2026-10-20 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('builds a snapshot with paid, lifetime, and free buckets', function () {
    $paidUser = User::factory()->withPersonalTeam()->create();
    $paidTeam = $paidUser->personalTeam();
    $paidTeam->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_paid',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly',
        'quantity' => 1,
        'created_at' => now()->subMonths(3),
    ]);

    $lifetimeUser = User::factory()->withPersonalTeam()->create();
    $lifetimeTeam = $lifetimeUser->personalTeam();
    $lifetimeTeam->update(['lifetime_at' => now()->subMonth()]);

    $freeUser = User::factory()->withPersonalTeam()->create();
    $freeTeam = $freeUser->personalTeam();

    $paidGallery = Gallery::factory()->for($paidTeam)->create();
    Photo::factory()->for($paidGallery)->create(['size' => 100, 'stored_size' => 100]);
    Photo::factory()->for($paidGallery)->create(['size' => 200, 'stored_size' => 200]);

    $lifetimeGallery = Gallery::factory()->for($lifetimeTeam)->create();
    Photo::factory()->for($lifetimeGallery)->create(['size' => 300, 'stored_size' => 300]);

    $freeGallery = Gallery::factory()->for($freeTeam)->create();
    Photo::factory()->for($freeGallery)->create(['size' => 400, 'stored_size' => 400]);

    Contract::factory()->for($paidTeam)->count(2)->create();
    Contract::factory()->for($freeTeam)->create();

    $stats = app(AdminUsageStatsService::class)->snapshot();

    expect($stats['photos'])->toBe(4)
        ->and($stats['storage_bytes'])->toBe(1000)
        ->and($stats['galleries'])->toBe(3)
        ->and($stats['contracts'])->toBe(3)
        ->and($stats['users'])->toBe(3)
        ->and($stats['paid_users'])->toBe(1)
        ->and($stats['lifetime_users'])->toBe(1)
        ->and($stats['free_users'])->toBe(1)
        ->and($stats['avg_photos_per_user'])->toBe(1.3)
        ->and($stats['avg_photos_per_paid_user'])->toBe(2.0)
        ->and($stats['avg_photos_per_lifetime_user'])->toBe(1.0)
        ->and($stats['avg_photos_per_free_user'])->toBe(1.0);
});

it('caches the snapshot until it is explicitly forgotten', function () {
    $service = app(AdminUsageStatsService::class);

    User::factory()->withPersonalTeam()->create();
    expect($service->snapshot()['users'])->toBe(1);

    User::factory()->withPersonalTeam()->create();
    expect($service->snapshot()['users'])->toBe(1);

    $service->forgetSnapshot();
    expect($service->snapshot()['users'])->toBe(2);
});

it('counts lifetime users with an active subscription as paid only', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->personalTeam();

    $team->update(['lifetime_at' => now()->subMonth()]);
    $team->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_lifetime_and_paid',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly',
        'quantity' => 1,
        'created_at' => now()->subWeek(),
    ]);

    $stats = app(AdminUsageStatsService::class)->snapshot();

    expect($stats['paid_users'])->toBe(1)
        ->and($stats['lifetime_users'])->toBe(0)
        ->and($stats['free_users'])->toBe(0);
});

it('builds monthly series for dashboard metrics', function () {
    $team = Team::factory()->create();
    $gallery = Gallery::factory()->for($team)->create();

    Photo::factory()->for($gallery)->create([
        'created_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(2),
    ]);
    Photo::factory()->for($gallery)->create([
        'created_at' => now()->startOfMonth()->addDay(),
    ]);

    $team->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_monthly_series',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly',
        'quantity' => 1,
        'created_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(3),
    ]);

    $lifetimeTeam = Team::factory()->create([
        'lifetime_at' => now()->startOfMonth()->addDays(4),
    ]);

    $service = app(AdminUsageStatsService::class);
    $from = now()->subMonthNoOverflow()->startOfMonth();
    $to = now()->endOfDay();

    $photos = $service->monthlySeries('photos', $from, $to);
    $paidUsers = $service->monthlySeries('paid_users', $from, $to);
    $lifetimeUsers = $service->monthlySeries('lifetime_users', $from, $to);
    $allSeries = $service->monthlySeriesForAll($from, $to);

    expect($allSeries)->toHaveKeys(['photos', 'galleries', 'contracts', 'users', 'paid_users', 'lifetime_users'])
        ->and($allSeries['photos'])->toBe($photos)
        ->and($photos)->toHaveCount(2)
        ->and($photos[0]['value'])->toBe(1)
        ->and($photos[1]['value'])->toBe(1)
        ->and($paidUsers[0]['value'])->toBe(1)
        ->and($paidUsers[1]['value'])->toBe(0)
        ->and($lifetimeUsers[0]['value'])->toBe(0)
        ->and($lifetimeUsers[1]['value'])->toBe(1)
        ->and($photos[1]['month'])->toBe(now()->format('Y-m'))
        ->and($photos[1]['label'])->toBe(now()->startOfMonth()->toDateString());
});

it('counts new records and new paid and lifetime users in a completed range', function () {
    $windowStart = now()->subDays(60)->startOfDay();
    $windowEnd = now()->subDays(30)->endOfDay();

    $paidUser = User::factory()->withPersonalTeam()->create();
    $paidUser->forceFill(['created_at' => $windowStart->copy()->addDay()])->saveQuietly();
    $paidTeam = $paidUser->personalTeam();
    $paidTeam->update(['created_at' => $windowStart->copy()->addDay()]);
    $paidTeam->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_in_range',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly',
        'quantity' => 1,
        'created_at' => $windowStart->copy()->addDays(2),
    ]);

    $gallery = Gallery::factory()->for($paidTeam)->create([
        'created_at' => $windowStart->copy()->addDays(3),
    ]);
    Photo::factory()->for($gallery)->create([
        'created_at' => $windowStart->copy()->addDays(4),
    ]);
    Contract::factory()->for($paidTeam)->create([
        'created_at' => $windowStart->copy()->addDays(5),
    ]);

    $lifetimeUser = User::factory()->withPersonalTeam()->create();
    $lifetimeUser->forceFill(['created_at' => $windowStart->copy()->addDays(6)])->saveQuietly();
    $lifetimeTeam = $lifetimeUser->personalTeam();
    $lifetimeTeam->update([
        'created_at' => $windowStart->copy()->addDays(6),
        'lifetime_at' => $windowStart->copy()->addDays(7),
    ]);

    $outsideUser = User::factory()->withPersonalTeam()->create();
    $outsideUser->forceFill(['created_at' => $windowStart->copy()->subDays(10)])->saveQuietly();
    $outsideTeam = $outsideUser->personalTeam();
    $outsideTeam->update(['created_at' => $windowStart->copy()->subDays(10)]);
    $outsideGallery = Gallery::factory()->for($outsideTeam)->create([
        'created_at' => $windowStart->copy()->subDays(9),
    ]);
    Photo::factory()->for($outsideGallery)->create([
        'created_at' => $windowStart->copy()->subDays(8),
    ]);
    Contract::factory()->for($outsideTeam)->create([
        'created_at' => $windowStart->copy()->subDays(7),
    ]);

    $stats = app(AdminUsageStatsService::class)->range($windowStart, $windowEnd);

    expect($stats['photos'])->toBe(1)
        ->and($stats['galleries'])->toBe(1)
        ->and($stats['contracts'])->toBe(1)
        ->and($stats['users'])->toBe(2)
        ->and($stats['paid_users'])->toBe(1)
        ->and($stats['lifetime_users'])->toBe(1);
});
