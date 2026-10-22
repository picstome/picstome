<?php

use App\Models\Contract;
use App\Models\Gallery;
use App\Models\Invoice;
use App\Models\Payment;
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

it('counts issued invoices, stripe payments, and onboarded stripe accounts', function () {
    $liveUser = User::factory()->withPersonalTeam()->create();
    $liveTeam = $liveUser->personalTeam();
    $liveTeam->update(['stripe_onboarded' => true]);

    $testUser = User::factory()->withPersonalTeam()->create();
    $testTeam = $testUser->personalTeam();
    $testTeam->update([
        'stripe_test_mode' => true,
        'stripe_test_onboarded' => true,
    ]);

    $pendingUser = User::factory()->withPersonalTeam()->create();
    $pendingTeam = $pendingUser->personalTeam();
    $pendingTeam->update(['stripe_account_id' => 'acct_pending']);

    Invoice::factory()->for($liveTeam)->issued()->create(['number' => 1]);
    Invoice::factory()->for($liveTeam)->create([
        'status' => Invoice::STATUS_RECTIFIED,
        'series' => 'F',
        'year' => (int) now()->year,
        'number' => 2,
        'issued_at' => now()->subDay(),
    ]);
    Invoice::factory()->for($liveTeam)->create();
    Invoice::factory()->for($testTeam)->issued()->create(['number' => 1]);

    Payment::factory()->for($liveTeam)->create([
        'stripe_payment_intent_id' => 'pi_live',
        'completed_at' => now(),
    ]);
    Payment::factory()->for($liveTeam)->create([
        'stripe_payment_intent_id' => 'fake_local',
        'completed_at' => now(),
    ]);
    Payment::factory()->for($liveTeam)->create([
        'stripe_payment_intent_id' => 'pi_pending',
        'completed_at' => null,
    ]);

    $stats = app(AdminUsageStatsService::class)->snapshot();

    expect($stats['invoices'])->toBe(3)
        ->and($stats['invoice_teams'])->toBe(2)
        ->and($stats['stripe_payments'])->toBe(1)
        ->and($stats['stripe_accounts'])->toBe(2)
        ->and($stats['accounts'])->toBe(3);
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

    Invoice::factory()->for($team)->issued()->create([
        'number' => 1,
        'issued_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(2),
    ]);
    Invoice::factory()->for($team)->issued()->create([
        'number' => 2,
        'issued_at' => now()->startOfMonth()->addDay(),
    ]);

    Payment::factory()->for($team)->create([
        'stripe_payment_intent_id' => 'pi_series_last_month',
        'completed_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(2),
    ]);
    Payment::factory()->for($team)->create([
        'stripe_payment_intent_id' => 'pi_series_current_month',
        'completed_at' => now()->startOfMonth()->addDay(),
    ]);

    $service = app(AdminUsageStatsService::class);
    $from = now()->subMonthNoOverflow()->startOfMonth();
    $to = now()->endOfDay();

    $photos = $service->monthlySeries('photos', $from, $to);
    $paidUsers = $service->monthlySeries('paid_users', $from, $to);
    $lifetimeUsers = $service->monthlySeries('lifetime_users', $from, $to);
    $invoices = $service->monthlySeries('invoices', $from, $to);
    $stripePayments = $service->monthlySeries('stripe_payments', $from, $to);
    $allSeries = $service->monthlySeriesForAll($from, $to);

    expect($allSeries)->toHaveKeys(['photos', 'galleries', 'contracts', 'users', 'paid_users', 'lifetime_users', 'invoices', 'stripe_payments'])
        ->and($allSeries['photos'])->toBe($photos)
        ->and($allSeries['invoices'])->toBe($invoices)
        ->and($allSeries['stripe_payments'])->toBe($stripePayments)
        ->and($photos)->toHaveCount(2)
        ->and($photos[0]['value'])->toBe(1)
        ->and($photos[1]['value'])->toBe(1)
        ->and($paidUsers[0]['value'])->toBe(1)
        ->and($paidUsers[1]['value'])->toBe(0)
        ->and($lifetimeUsers[0]['value'])->toBe(0)
        ->and($lifetimeUsers[1]['value'])->toBe(1)
        ->and($invoices[0]['value'])->toBe(1)
        ->and($invoices[1]['value'])->toBe(1)
        ->and($stripePayments[0]['value'])->toBe(1)
        ->and($stripePayments[1]['value'])->toBe(1)
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
    Invoice::factory()->for($paidTeam)->issued()->create([
        'number' => 1,
        'issued_at' => $windowStart->copy()->addDays(6),
    ]);
    Payment::factory()->for($paidTeam)->create([
        'stripe_payment_intent_id' => 'pi_in_range',
        'completed_at' => $windowStart->copy()->addDays(6),
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
    Invoice::factory()->for($outsideTeam)->issued()->create([
        'number' => 1,
        'issued_at' => $windowStart->copy()->subDays(6),
    ]);
    Payment::factory()->for($outsideTeam)->create([
        'stripe_payment_intent_id' => 'pi_outside_range',
        'completed_at' => $windowStart->copy()->subDays(6),
    ]);

    $stats = app(AdminUsageStatsService::class)->range($windowStart, $windowEnd);

    expect($stats['photos'])->toBe(1)
        ->and($stats['galleries'])->toBe(1)
        ->and($stats['contracts'])->toBe(1)
        ->and($stats['users'])->toBe(2)
        ->and($stats['paid_users'])->toBe(1)
        ->and($stats['lifetime_users'])->toBe(1)
        ->and($stats['invoices'])->toBe(1)
        ->and($stats['invoice_teams'])->toBe(1)
        ->and($stats['stripe_payments'])->toBe(1);
});
