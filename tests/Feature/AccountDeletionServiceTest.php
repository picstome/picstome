<?php

use App\Jobs\PurgeUserAccount;
use App\Jobs\RemoveFromAcumbamailList;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\ExternalAuthorization;
use App\Models\FtpAccount;
use App\Models\Gallery;
use App\Models\GalleryArchive;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Photo;
use App\Models\PhotoVersion;
use App\Models\Team;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->disk = config('picstome.disk');
    Storage::fake($this->disk);
    Storage::fake('archives');
    Storage::fake(FtpAccount::diskName());

    config([
        'picstome.gallery_archives.disk' => 'archives',
        'picstome.ftp_uploads.sftpgo_url' => null,
        'services.acumbamail.auth_token' => null,
    ]);
});

/**
 * Build an account with one of everything the purge has to touch.
 *
 * @return array{user: User, team: Team, gallery: Gallery, photo: Photo}
 */
function deletableAccount(): array
{
    $user = User::factory()->withPersonalTeam()->create([
        'avatar_path' => 'avatars/avatar.jpg',
    ]);

    Storage::disk(config('picstome.disk'))->put('avatars/avatar.jpg', 'avatar');

    $team = $user->personalTeam();
    $gallery = Gallery::factory()->for($team)->create();
    $photo = Photo::factory()->for($gallery)->create([
        // The factory leaves `disk` null, which means the `public` disk. The
        // fixtures below store objects on the configured disk instead.
        'disk' => config('picstome.disk'),
        'path' => 'teams/'.$team->id.'/galleries/x/photos/photo.jpg',
        'thumb_path' => 'teams/'.$team->id.'/galleries/x/photos/thumb.jpg',
        'size' => 1024,
        'stored_size' => 1024,
    ]);

    Storage::disk(config('picstome.disk'))->put($photo->path, 'photo');
    Storage::disk(config('picstome.disk'))->put($photo->thumb_path, 'thumb');

    PhotoVersion::factory()->for($photo)->create([
        // Explicit number: the factory picks 1-5 at random and the table has a
        // composite unique index on (photo_id, version_number).
        'version_number' => 1,
        'path' => 'teams/'.$team->id.'/galleries/x/photos/version.jpg',
        'size' => 512,
        'stored_size' => 512,
    ]);

    Contract::factory()->for($team)->create();
    Customer::factory()->for($team)->create();
    Payment::factory()->for($team)->create();
    Invoice::factory()->for($team)->create();

    return compact('user', 'team', 'gallery', 'photo');
}

it('deletes every row and stored object owned by the account', function () {
    ['user' => $user, 'team' => $team, 'gallery' => $gallery, 'photo' => $photo] = deletableAccount();

    $user->createToken('picstome-sync-abc123', ['sync']);
    ExternalAuthorization::create([
        'user_id' => $user->id,
        'client_name' => 'picstome-sync',
        'code_hash' => hash('sha256', 'code'),
        'nonce' => 'nonce',
        'expires_at' => now()->addHour(),
    ]);

    DB::table('sessions')->insert([
        'id' => 'session-1',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Mozilla/5.0',
        'payload' => 'payload',
        'last_activity' => time(),
    ]);

    app(AccountDeletionService::class)->purge($user);

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
    expect(Team::query()->whereKey($team->id)->exists())->toBeFalse();
    expect(Gallery::query()->whereKey($gallery->id)->exists())->toBeFalse();
    expect(Photo::query()->whereKey($photo->id)->exists())->toBeFalse();
    expect(PhotoVersion::query()->where('photo_id', $photo->id)->exists())->toBeFalse();
    expect(DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->exists())->toBeFalse();
    expect(ExternalAuthorization::query()->where('user_id', $user->id)->exists())->toBeFalse();
    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();

    Storage::disk(config('picstome.disk'))->assertMissing('avatars/avatar.jpg');

    foreach ([$photo->path, $photo->thumb_path] as $path) {
        Storage::disk(config('picstome.disk'))->assertMissing($path);
    }
});

it('deletes issued invoices that the model protects from deletion', function () {
    ['user' => $user, 'team' => $team] = deletableAccount();

    $issued = Invoice::factory()->for($team)->issued()->create();

    expect(fn () => $issued->delete())->toThrow(LogicException::class);

    app(AccountDeletionService::class)->purge($user);

    expect(Invoice::query()->where('team_id', $team->id)->exists())->toBeFalse();
});

it('leaves other accounts untouched', function () {
    ['user' => $user] = deletableAccount();
    ['user' => $otherUser, 'team' => $otherTeam, 'gallery' => $otherGallery] = deletableAccount();

    app(AccountDeletionService::class)->purge($user);

    expect(User::query()->whereKey($otherUser->id)->exists())->toBeTrue();
    expect(Team::query()->whereKey($otherTeam->id)->exists())->toBeTrue();
    expect(Gallery::query()->whereKey($otherGallery->id)->exists())->toBeTrue();
});

it('reports Stripe failures without blocking the deletion', function () {
    ['user' => $user, 'team' => $team] = deletableAccount();

    config(['cashier.secret' => 'sk_test_fake']);

    $team->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_fake',
        'stripe_status' => 'active',
        'stripe_price' => 'price_fake',
        'quantity' => 1,
    ]);

    app(AccountDeletionService::class)->purge($user);

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
    expect(DB::table('subscriptions')->where('team_id', $team->id)->exists())->toBeFalse();
});

it('removes the SFTPGo user and the pending camera uploads', function () {
    ['user' => $user, 'team' => $team] = deletableAccount();

    config([
        'picstome.ftp_uploads.sftpgo_url' => 'https://sftpgo.test',
        'picstome.ftp_uploads.sftpgo_api_key' => 'key',
    ]);

    Http::fake(['sftpgo.test/*' => Http::response([], 204)]);

    $account = FtpAccount::factory()->for($team)->create(['username' => '123456']);
    Storage::disk(FtpAccount::diskName())->put($account->inboxPath().'/DCIM/a.jpg', 'bytes');

    app(AccountDeletionService::class)->purge($user);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), '/api/v2/users/123456'));

    expect(FtpAccount::query()->whereKey($account->id)->exists())->toBeFalse();
    Storage::disk(FtpAccount::diskName())->assertMissing($account->inboxPath().'/DCIM/a.jpg');
});

it('queues the Acumbamail removal', function () {
    ['user' => $user] = deletableAccount();

    // The removal job is dispatched inside the purge; the fake queue keeps the
    // HTTP client untouched while still recording the dispatch.
    Queue::fake([RemoveFromAcumbamailList::class]);

    app(AccountDeletionService::class)->purge($user);

    Queue::assertPushed(RemoveFromAcumbamailList::class, fn (RemoveFromAcumbamailList $job) => $job->email === $user->email);
});

it('revokes tokens and other sessions before the purge is queued', function () {
    ['user' => $user] = deletableAccount();

    $token = $user->createToken('picstome-sync-abc123', ['sync']);

    DB::table('sessions')->insert([
        'id' => 'other-session',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Mozilla/5.0',
        'payload' => 'payload',
        'last_activity' => time(),
    ]);

    app(AccountDeletionService::class)->revokeAccess($user);

    expect(DB::table('personal_access_tokens')->where('id', $token->accessToken->id)->exists())->toBeFalse();
    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();
});

it('deletes the account through the queued job', function () {
    ['user' => $user] = deletableAccount();

    (new PurgeUserAccount($user->id))->handle(app(AccountDeletionService::class));

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
});

it('does nothing when the account is already gone', function () {
    (new PurgeUserAccount(999999))->handle(app(AccountDeletionService::class));

    expect(User::query()->whereKey(999999)->exists())->toBeFalse();
});

it('summarises what the account holds', function () {
    ['user' => $user, 'team' => $team] = deletableAccount();

    $inventory = app(AccountDeletionService::class)->inventory($user);

    expect($inventory['galleries'])->toBe(1)
        ->and($inventory['photos'])->toBe(1)
        ->and($inventory['contracts'])->toBe(1)
        ->and($inventory['invoices'])->toBe(1)
        ->and($inventory['issued_invoices'])->toBe(0)
        ->and($inventory['customers'])->toBe(1)
        ->and($inventory['payments'])->toBe(1)
        ->and($inventory['subscribed'])->toBeFalse()
        ->and($inventory['lifetime'])->toBeFalse();
});

it('reports a subscription that will be cancelled', function () {
    ['user' => $user, 'team' => $team] = deletableAccount();

    $team->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_fake',
        'stripe_status' => 'active',
        'ends_at' => now()->addDays(10),
    ]);

    $inventory = app(AccountDeletionService::class)->inventory($user);

    expect($inventory['subscribed'])->toBeTrue()
        ->and($inventory['subscription_ends_at'])->toBe(now()->addDays(10)->toDateString());
});

it('deletes files of versions stored on a different disk than their photo', function () {
    Storage::fake('second');

    ['user' => $user, 'photo' => $photo] = deletableAccount();

    $version = PhotoVersion::factory()->for($photo)->create([
        'version_number' => 2,
        'disk' => 'second',
        'path' => 'teams/1/galleries/x/photos/v.jpg',
        'raw_path' => null,
        'thumb_path' => null,
    ]);

    Storage::disk('second')->put($version->path, 'x');

    app(AccountDeletionService::class)->purge($user);

    Storage::disk('second')->assertMissing($version->path);
});

it('deletes files stored under a non canonical team prefix', function () {
    ['user' => $user, 'team' => $team, 'photo' => $photo] = deletableAccount();

    $disk = config('picstome.disk');
    $imported = "docs-demo/teams/{$team->id}/galleries/x/photos/imported.jpg";

    $photo->update(['path' => $imported]);
    Storage::disk($disk)->put($imported, 'x');

    app(AccountDeletionService::class)->purge($user);

    Storage::disk($disk)->assertMissing($imported);
    Storage::disk($disk)->assertMissing("docs-demo/teams/{$team->id}");
});

it('cancels each stripe subscription before removing the rows', function () {
    config(['cashier.secret' => 'sk_test_audit']);

    ['user' => $user, 'team' => $team] = deletableAccount();

    $team->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_audit',
        'stripe_status' => 'active',
        'stripe_price' => 'price_audit',
        'quantity' => 1,
    ]);

    // A real Stripe call cannot happen in tests, so the service must swallow
    // the failure, report it, and still remove the local rows.
    Log::spy();

    app(AccountDeletionService::class)->purge($user);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'cancel stripe subscription'))
        ->once();

    expect(DB::table('subscriptions')->where('team_id', $team->id)->exists())->toBeFalse();
});

it('does not try stripe when the platform key is missing', function () {
    config(['cashier.secret' => null]);

    ['user' => $user, 'team' => $team] = deletableAccount();

    $team->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_audit',
        'stripe_status' => 'active',
        'stripe_price' => 'price_audit',
        'quantity' => 1,
    ]);

    Log::spy();

    app(AccountDeletionService::class)->purge($user);

    Log::shouldNotHaveReceived('warning');
    expect(DB::table('subscriptions')->where('team_id', $team->id)->exists())->toBeFalse();
});

it('does not truncate a longer team id to the deleted one', function () {
    ['user' => $user, 'team' => $team, 'photo' => $photo] = deletableAccount();

    $disk = config('picstome.disk');

    // The neighbour keeps its own library.
    $neighbourId = (int) ($team->id.'0');
    $neighbourPath = "teams/{$neighbourId}/galleries/x/photos/keep.jpg";
    Storage::disk($disk)->put($neighbourPath, 'keep');

    // The deleted team's photo sits in its own directory, as it should.
    Storage::disk($disk)->put($photo->path, 'own');

    app(AccountDeletionService::class)->purge($user);

    Storage::disk($disk)->assertExists($neighbourPath);
    Storage::disk($disk)->assertMissing($photo->path);
});

it('never deletes the disk root for an unknown path', function () {
    ['user' => $user, 'photo' => $photo] = deletableAccount();

    $disk = config('picstome.disk');
    Storage::disk($disk)->put('unrelated/keep.jpg', 'keep');

    $photo->update(['path' => 'bare-file.jpg', 'thumb_path' => null, 'raw_path' => null]);
    Storage::disk($disk)->put('bare-file.jpg', 'x');

    app(AccountDeletionService::class)->purge($user);

    Storage::disk($disk)->assertExists('unrelated/keep.jpg');
});

it('removes archive zips that live on their own disk', function () {
    Storage::fake('archives');
    config(['picstome.gallery_archives.disk' => 'archives']);

    ['user' => $user, 'team' => $team, 'gallery' => $gallery] = deletableAccount();

    $archive = GalleryArchive::factory()->for($gallery)->for($team)->create([
        'disk' => 'archives',
        'path' => "teams/{$team->id}/galleries/{$gallery->ulid}/archives/x.zip",
        'storage_deleted_at' => null,
    ]);

    Storage::disk('archives')->put($archive->path, 'zip');

    app(AccountDeletionService::class)->purge($user);

    Storage::disk('archives')->assertMissing($archive->path);
});

it('deletes an individual file when its path has no team segment', function () {
    ['user' => $user, 'photo' => $photo] = deletableAccount();

    $disk = config('picstome.disk');

    // A legacy path outside `teams/{id}`: only the file itself may be
    // deleted, never its parent (which could be shared with other teams).
    $photo->update(['path' => 'legacy-uploads/a.jpg', 'thumb_path' => null, 'raw_path' => null]);
    Storage::disk($disk)->put('legacy-uploads/a.jpg', 'x');
    Storage::disk($disk)->put('legacy-uploads/other-team.jpg', 'keep');

    app(AccountDeletionService::class)->purge($user);

    Storage::disk($disk)->assertMissing('legacy-uploads/a.jpg');
    Storage::disk($disk)->assertExists('legacy-uploads/other-team.jpg');
});

it('is safe to run twice, so a retried job cannot fail', function () {
    ['user' => $user, 'team' => $team] = deletableAccount();

    $service = app(AccountDeletionService::class);

    $service->purge($user);

    // A second attempt (job retry, duplicate dispatch) must be a no-op.
    $service->purge($user->fresh() ?? $user);

    expect(Team::query()->whereKey($team->id)->exists())->toBeFalse();
});

it('does not fail when the queue job runs after the account is gone', function () {
    ['user' => $user] = deletableAccount();

    $service = app(AccountDeletionService::class);

    $service->purge($user);

    // The job re-reads the user; a missing row must not throw.
    (new PurgeUserAccount($user->id))->handle($service);

    expect(true)->toBeTrue();
});

it('deletes a user that has no team', function () {
    $user = User::factory()->create(['avatar_path' => null]);

    expect($user->personalTeam())->toBeNull();

    app(AccountDeletionService::class)->purge($user);

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
});

it('deletes legacy photos whose disk is null (the public disk)', function () {
    Storage::fake('public');

    ['user' => $user, 'team' => $team] = deletableAccount();

    $gallery = Gallery::factory()->for($team)->create();
    $legacy = Photo::factory()->for($gallery)->create([
        'disk' => null,
        'path' => "teams/{$team->id}/galleries/legacy/photos/old.jpg",
        'thumb_path' => null,
        'raw_path' => null,
    ]);

    Storage::disk('public')->put($legacy->path, 'x');

    app(AccountDeletionService::class)->purge($user);

    Storage::disk('public')->assertMissing($legacy->path);
});

it('handles storage paths with a leading slash safely', function () {
    ['user' => $user, 'photo' => $photo] = deletableAccount();

    $disk = config('picstome.disk');
    $photo->update(['path' => '/'.$photo->path]);
    Storage::disk($disk)->put(ltrim($photo->path, '/'), 'x');

    app(AccountDeletionService::class)->purge($user);

    Storage::disk($disk)->assertMissing(ltrim($photo->path, '/'));
});

it('only deletes rows and files that belong to the target account', function () {
    Storage::fake('archives');
    config([
        'picstome.gallery_archives.disk' => 'archives',
        'cashier.secret' => null,
    ]);

    $target = deletableAccount();
    $other = deletableAccount();

    // Give the other account the same kind of archive and FTP account.
    $otherArchive = GalleryArchive::factory()
        ->for($other['gallery'])->for($other['team'])
        ->create([
            'disk' => 'archives',
            'path' => "teams/{$other['team']->id}/galleries/x/archives/keep.zip",
            'storage_deleted_at' => null,
        ]);
    Storage::disk('archives')->put($otherArchive->path, 'zip');

    $otherFtp = FtpAccount::factory()->for($other['team'])->create(['username' => '654321']);
    Storage::disk(FtpAccount::diskName())->put($otherFtp->inboxPath().'/keep.jpg', 'x');

    $otherToken = $other['user']->createToken('picstome-sync-keep', ['sync']);
    ExternalAuthorization::create([
        'user_id' => $other['user']->id,
        'client_name' => 'keep',
        'code_hash' => hash('sha256', 'keep'),
        'nonce' => 'n',
        'expires_at' => now()->addHour(),
    ]);
    DB::table('sessions')->insert([
        'id' => 'other-session', 'user_id' => $other['user']->id,
        'ip_address' => '127.0.0.1', 'user_agent' => 'ua', 'payload' => 'p',
        'last_activity' => time(),
    ]);

    app(AccountDeletionService::class)->purge($target['user']);

    // Every row of the untouched account is still there.
    expect(User::query()->whereKey($other['user']->id)->exists())->toBeTrue()
        ->and(Team::query()->whereKey($other['team']->id)->exists())->toBeTrue()
        ->and(Gallery::query()->whereKey($other['gallery']->id)->exists())->toBeTrue()
        ->and(Photo::query()->whereKey($other['photo']->id)->exists())->toBeTrue()
        ->and(PhotoVersion::query()->where('photo_id', $other['photo']->id)->exists())->toBeTrue()
        ->and(Contract::query()->where('team_id', $other['team']->id)->exists())->toBeTrue()
        ->and(Customer::query()->where('team_id', $other['team']->id)->exists())->toBeTrue()
        ->and(Invoice::query()->where('team_id', $other['team']->id)->exists())->toBeTrue()
        ->and(Payment::query()->where('team_id', $other['team']->id)->exists())->toBeTrue()
        ->and(GalleryArchive::query()->whereKey($otherArchive->id)->exists())->toBeTrue()
        ->and(FtpAccount::query()->whereKey($otherFtp->id)->exists())->toBeTrue();

    // ...and so are its tokens, sessions and files.
    expect(DB::table('personal_access_tokens')->where('id', $otherToken->accessToken->id)->exists())->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'other-session')->exists())->toBeTrue()
        ->and(DB::table('external_authorizations')->where('user_id', $other['user']->id)->exists())->toBeTrue();

    Storage::disk(config('picstome.disk'))->assertExists($other['photo']->path);
    Storage::disk(config('picstome.disk'))->assertExists($other['photo']->thumb_path);
    Storage::disk('archives')->assertExists($otherArchive->path);
    Storage::disk(FtpAccount::diskName())->assertExists($otherFtp->inboxPath().'/keep.jpg');
});
