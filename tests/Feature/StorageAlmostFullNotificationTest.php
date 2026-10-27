<?php

use App\Events\PhotoAdded;
use App\Listeners\NotifyStorageAlmostFull;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Team;
use App\Models\User;
use App\Notifications\StorageAlmostFull;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * Create an owner and team whose billable storage is $used out of $limit.
 *
 * @return array{0: User, 1: Team, 2: Photo}
 */
function storageAlertFixture(int $limit = 1000, int $used = 950): array
{
    $owner = User::factory()->withPersonalTeam()->create();
    $team = $owner->personalTeam();

    $team->update(['custom_storage_limit' => $limit]);

    $photo = Photo::factory()
        ->for(Gallery::factory()->for($team))
        ->create([
            'size' => $used,
            'stored_size' => $used,
        ]);

    return [$owner, $team, $photo];
}

it('sends the storage email once the team reaches the threshold', function () {
    Notification::fake();

    [$owner, $team, $photo] = storageAlertFixture();

    (new NotifyStorageAlmostFull)->handle(new PhotoAdded($photo));

    Notification::assertSentTo(
        $owner,
        StorageAlmostFull::class,
        fn (StorageAlmostFull $notification): bool => $notification->used === 950
            && $notification->limit === 1000
            && $notification->upgradeUrl === route('subscribe')
    );

    expect($team->fresh()->storage_limit_notification_sent_at)->not->toBeNull();
});

it('listens to photo uploads', function () {
    Notification::fake();
    Queue::fake();

    [$owner, , $photo] = storageAlertFixture();

    PhotoAdded::dispatch($photo);

    Notification::assertSentTo($owner, StorageAlmostFull::class);
});

it('does not send the storage email below the threshold', function () {
    Notification::fake();

    [$owner, $team] = storageAlertFixture(1000, 900);

    $team->notifyOwnerWhenStorageAlmostFull();

    Notification::assertNotSentTo($owner, StorageAlmostFull::class);
});

it('does not send the storage email for unlimited storage', function () {
    Notification::fake();

    [$owner, $team] = storageAlertFixture(1000, 950);
    $team->update(['custom_storage_limit' => null]);

    $team->notifyOwnerWhenStorageAlmostFull();

    Notification::assertNotSentTo($owner, StorageAlmostFull::class);
});

it('sends the storage email only once per plan', function () {
    Notification::fake();

    [$owner, $team] = storageAlertFixture();

    $team->notifyOwnerWhenStorageAlmostFull();
    $team->notifyOwnerWhenStorageAlmostFull();

    Notification::assertSentToTimes($owner, StorageAlmostFull::class, 1);

    $team->update(['custom_storage_limit' => 2000]);
    expect($team->fresh()->storage_limit_notification_sent_at)->toBeNull();

    $team->update(['custom_storage_limit' => 1000]);
    $team->notifyOwnerWhenStorageAlmostFull();

    Notification::assertSentToTimes($owner, StorageAlmostFull::class, 2);
});

it('uses the owner language', function () {
    Notification::fake();

    [$owner, $team] = storageAlertFixture();
    $owner->update(['language' => 'es']);

    $team->notifyOwnerWhenStorageAlmostFull();

    Notification::assertSentTo(
        $owner,
        StorageAlmostFull::class,
        fn (StorageAlmostFull $notification): bool => $notification->locale === 'es'
    );
});

it('links subscribed teams to the billing portal', function () {
    [$owner, $team] = storageAlertFixture();

    $team->update(['lifetime_at' => now()]);

    expect($team->storageUpgradeUrl())->toBe(route('billing-portal'));
});

it('renders the storage email with translated copy', function () {
    app()->setLocale('es');

    [$owner] = storageAlertFixture();

    $mail = (new StorageAlmostFull(950, 1000, route('subscribe')))->toMail($owner);

    expect($mail->subject)->toBe('Tu almacenamiento está casi lleno')
        ->and($mail->actionUrl)->toBe(route('subscribe'))
        ->and((string) $mail->render())
        ->toContain('Has usado 950 B de 1000 B de almacenamiento.')
        ->toContain('Elige un plan con más almacenamiento')
        ->toContain('Ver planes');
});
