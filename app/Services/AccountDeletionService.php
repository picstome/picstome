<?php

namespace App\Services;

use App\Jobs\RemoveFromAcumbamailList;
use App\Models\ExternalAuthorization;
use App\Models\FtpAccount;
use App\Models\Gallery;
use App\Models\GalleryArchive;
use App\Models\Invoice;
use App\Models\Photo;
use App\Models\PhotoVersion;
use App\Models\Team;
use App\Models\User;
use App\Traits\FormatsFileSize;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Cashier\Cashier;
use Throwable;

/**
 * Irreversible removal of a user and everything their teams own.
 *
 * The purge is orchestrated here instead of in the model because it mixes
 * database rows, object storage, Stripe and SFTPGo, and every external call is
 * best-effort: a Stripe or SFTPGo outage must never stop a user from closing
 * their account. Failures are reported so the data can be cleaned by hand.
 *
 * The heavy work runs inside `PurgeUserAccount`; the request only revokes
 * access and dispatches the job.
 */
class AccountDeletionService
{
    use FormatsFileSize;

    /**
     * Everything the confirmation modal shows the user before deleting.
     *
     * Counts run against the database only (no storage listings) so opening
     * the modal stays cheap even for large accounts.
     *
     * @return array{
     *     galleries: int,
     *     photos: int,
     *     storage_bytes: int,
     *     contracts: int,
     *     invoices: int,
     *     issued_invoices: int,
     *     customers: int,
     *     payments: int,
     *     subscribed: bool,
     *     subscription_ends_at: string|null,
     *     lifetime: bool,
     *     has_ftp: bool,
     *     app_tokens: int,
     * }
     */
    public function inventory(User $user): array
    {
        $teams = $user->ownedTeams()->get();
        $teamIds = $teams->pluck('id')->all();

        $galleries = Gallery::query()->whereIn('team_id', $teamIds)->count();
        $photos = Photo::query()
            ->whereIn('gallery_id', Gallery::query()->select('id')->whereIn('team_id', $teamIds))
            ->count();

        $subscription = $teams
            ->map(fn (Team $team) => $team->subscription('default'))
            ->filter()
            ->sortByDesc(fn ($subscription) => $subscription->ends_at)
            ->first();

        return [
            'galleries' => $galleries,
            'photos' => $photos,
            'storage_bytes' => (int) $teams->sum(fn (Team $team) => StorageUsageService::forTeamFresh($team)),
            'contracts' => (int) $teams->sum(fn (Team $team) => $team->contracts()->count()),
            'invoices' => Invoice::query()->whereIn('team_id', $teamIds)->count(),
            'issued_invoices' => Invoice::query()
                ->whereIn('team_id', $teamIds)
                ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_RECTIFIED])
                ->count(),
            'customers' => (int) $teams->sum(fn (Team $team) => $team->customers()->count()),
            'payments' => (int) $teams->sum(fn (Team $team) => $team->payments()->count()),
            'subscribed' => $subscription !== null && $subscription->valid(),
            'subscription_ends_at' => $subscription?->ends_at?->toDateString(),
            'lifetime' => $teams->contains(fn (Team $team) => $team->lifetime_at !== null),
            'has_ftp' => $teams->contains(fn (Team $team) => $team->ftpAccount()->exists()),
            'app_tokens' => $user->tokens()->count(),
        ];
    }

    /**
     * Revoke every way into the account before the purge is queued.
     *
     * The purge can wait in the queue for a while; deletion must not. Revoking
     * tokens, pending external authorizations and other sessions here closes
     * that window, and it is cheap.
     */
    public function revokeAccess(User $user): void
    {
        $user->tokens()->delete();

        // A pending Picstome Sync code could still be exchanged for a fresh
        // token while the purge job waits in the queue.
        ExternalAuthorization::query()->where('user_id', $user->id)->delete();

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', session()->getId())
            ->delete();
    }

    /**
     * Human readable size of everything the account stores.
     */
    public function formattedStorage(int $bytes): string
    {
        return $this->formatFileSize($bytes);
    }

    /**
     * Delete the user, every owned team and all of their resources.
     *
     * The caller is responsible for having revoked the user's sessions and
     * tokens first; this method only guarantees the stored data is gone.
     */
    public function purge(User $user): void
    {
        $teams = $user->ownedTeams()->get();

        foreach ($teams as $team) {
            // Read the storage fingerprints before the rows disappear.
            $paths = $this->storagePathsUsedBy($team);

            $this->cancelSubscriptions($team);
            $this->removeFtpAccount($team);

            $team->deleteAllResources();

            $this->deleteStoredPaths($paths['directories'] ?? []);
            $this->deleteOrphanFiles($paths['files'] ?? []);
            $this->deleteSubscriptions($team);

            $team->delete();
        }

        $this->deleteUserRecords($user);
    }

    /**
     * Every disk + directory prefix that may hold files for the team.
     *
     * The canonical prefix is `teams/{id}`, but the storage path is derived
     * from data (seeded/imported libraries store under a different root, for
     * example `docs-demo/teams/{id}`). Relying only on `teams/{id}` would
     * leave those objects in the bucket forever, so the actual directories are
     * read from the rows before they are deleted.
     *
     * Photos and their versions each record their own disk: a version written
     * to a different disk than its photo would also be missed.
     *
     * Paths that carry no `teams/{id}` segment are returned as individual
     * files rather than collapsed into their parent directory, because that
     * parent may be shared with other teams.
     *
     * @return array{directories: list<array{disk: string, path: string}>, files: list<array{disk: string, path: string}>}
     */
    protected function storagePathsUsedBy(Team $team): array
    {
        $galleryIds = Gallery::query()->where('team_id', $team->id)->select('id');
        $photoIds = Photo::query()->whereIn('gallery_id', $galleryIds)->select('id');
        $paths = [];

        $add = function (?string $disk, ?string $path) use (&$paths, $team): void {
            if (blank($path)) {
                return;
            }

            $disk = $disk ?: (string) config('picstome.disk');

            // Collapse to the team's storage root. Libraries imported with a
            // different prefix (eg. `docs-demo/teams/8/...`) must not leave
            // their parent directories behind, and the canonical layout puts
            // every asset under `.../teams/{id}`.
            $root = $this->teamStorageRoot($path, $team->id);

            if ($root === null) {
                $paths['files'][$disk.'|'.$path] = ['disk' => $disk, 'path' => $path];

                return;
            }

            $paths['directories'][$disk.'|'.$root] = ['disk' => $disk, 'path' => $root];
        };

        Photo::query()
            ->whereIn('gallery_id', $galleryIds)
            ->select(['id', 'disk', 'path', 'raw_path', 'thumb_path'])
            ->cursor()
            ->each(function (Photo $photo) use ($add): void {
                // `diskOrDefault()` returns `public` for legacy rows with a
                // null disk; falling back to the configured disk instead would
                // look for the file on the wrong disk and leave it behind.
                $disk = $photo->diskOrDefault();

                $add($disk, $photo->path);
                $add($disk, $photo->raw_path);
                $add($disk, $photo->thumb_path);
            });

        PhotoVersion::query()
            ->whereIn('photo_id', $photoIds)
            ->select(['id', 'disk', 'path', 'raw_path', 'thumb_path'])
            ->cursor()
            ->each(function (PhotoVersion $version) use ($add): void {
                $disk = $version->diskOrDefault();

                $add($disk, $version->path);
                $add($disk, $version->raw_path);
                $add($disk, $version->thumb_path);
            });

        // Brand assets, contracts, signatures and invoice PDFs live under the
        // team directory on the configured disk.
        $add(config('picstome.disk'), "teams/{$team->id}/placeholder");

        // Gallery ZIPs live under `teams/{id}/...` too, but on their own disk
        // (which may differ from the photo disk). The gallery deletion hook
        // queues a job per archive; adding the root here makes the cleanup
        // immediate even when the queue is behind or stopped.
        GalleryArchive::query()
            ->where('team_id', $team->id)
            ->whereNotNull('disk')
            ->distinct()
            ->pluck('disk')
            ->each(function (string $disk) use ($add, $team): void {
                $add($disk, "teams/{$team->id}/placeholder");
            });

        return [
            'directories' => array_values($paths['directories'] ?? []),
            'files' => array_values($paths['files'] ?? []),
        ];
    }

    /**
     * Delete the files whose parent directory may be shared with other teams.
     *
     * @param  list<array{disk: string, path: string}>  $files
     */
    protected function deleteOrphanFiles(array $files): void
    {
        foreach ($files as $file) {
            try {
                Storage::disk($file['disk'])->delete($file['path']);
            } catch (Throwable $exception) {
                $this->reportFailure('delete stored file', $file, $exception);
            }
        }
    }

    /**
     * The team directory that contains a stored object.
     *
     * The canonical layout is `teams/{id}/...`, but imported libraries may
     * live under a different root (`docs-demo/teams/8/...`). Walking back to
     * the `teams/{id}` segment keeps the deletion scoped to that team even
     * when the prefix differs.
     *
     * The segment boundary matters: a naive `strpos` for `teams/7` also
     * matches `teams/70`, so deleting team 7 would wipe team 70's files. And
     * an unrecognised path must never resolve to the bucket root.
     */
    protected function teamStorageRoot(string $path, int|string $teamId): ?string
    {
        // Storage keys never start with a slash. Normalising here keeps the
        // returned directory valid for the disk API if a legacy row has one.
        $path = ltrim($path, '/');

        $needle = "teams/{$teamId}";
        $position = strpos($path, $needle);

        if ($position !== false) {
            $end = $position + strlen($needle);

            // Only accept `teams/{id}` followed by a separator or the end.
            $next = $path[$end] ?? null;

            if ($next === null || $next === '/') {
                return substr($path, 0, $end);
            }
        }

        // No recognisable team segment. Returning the parent directory would
        // be dangerous: a shared prefix such as `uploads/` belongs to every
        // team, so the caller deletes these paths one file at a time instead.
        return null;
    }

    /**
     * Delete the collected directories, one failing disk never aborting the
     * rest of the purge.
     *
     * @param  list<array{disk: string, path: string}>  $paths
     */
    protected function deleteStoredPaths(array $paths): void
    {
        foreach ($paths as $entry) {
            try {
                if (trim($entry['path'], '/') === '') {
                    continue;
                }

                Storage::disk($entry['disk'])->deleteDirectory($entry['path']);
            } catch (Throwable $exception) {
                $this->reportFailure('delete stored directory', $entry, $exception);
            }
        }
    }

    /**
     * Cancel Stripe before the local rows vanish, so the customer is never
     * billed again. A failure is reported and does not block the deletion.
     *
     * Cashier cancels through the platform key (`cashier.secret`), not through
     * the team's Connect key: a team-level key would be the wrong account for
     * a subscription billed by Picstome itself. Without that key there is
     * nothing to cancel, but the local rows are still removed below.
     */
    protected function cancelSubscriptions(Team $team): void
    {
        $model = Cashier::$subscriptionModel;

        $subscriptions = $model::query()
            ->where($team->getForeignKey(), $team->getKey())
            ->get();

        foreach ($subscriptions as $subscription) {
            if (! config('cashier.secret') || blank($subscription->stripe_id)) {
                continue;
            }

            try {
                $subscription->cancelNow();
            } catch (Throwable $exception) {
                $this->reportFailure('cancel stripe subscription', [
                    'team' => $team->id,
                    'subscription' => $subscription->stripe_id,
                ], $exception);
            }
        }
    }

    /**
     * Remove the SFTPGo user, the files still waiting in its inbox and then
     * the local account row.
     */
    protected function removeFtpAccount(Team $team): void
    {
        $account = $team->ftpAccount()->first();

        if (! $account) {
            return;
        }

        try {
            app(SftpgoService::class)->deleteUser($account);
        } catch (Throwable $exception) {
            $this->reportFailure('delete SFTPGo user', ['team' => $team->id], $exception);
        }

        try {
            $account->purgeInbox();
        } catch (Throwable $exception) {
            $this->reportFailure('purge FTP inbox', ['team' => $team->id], $exception);
        }

        try {
            Storage::disk(FtpAccount::diskName())->deleteDirectory($account->inboxPath());
        } catch (Throwable $exception) {
            $this->reportFailure('delete FTP inbox directory', ['team' => $team->id], $exception);
        }

        $account->delete();
    }

    /**
     * Cashier keeps subscriptions and their items without a foreign key to
     * the team, so they have to be removed explicitly.
     */
    protected function deleteSubscriptions(Team $team): void
    {
        $model = Cashier::$subscriptionModel;

        $subscriptions = $model::query()
            ->where($team->getForeignKey(), $team->getKey())
            ->get();

        foreach ($subscriptions as $subscription) {
            $subscription->items()->delete();
            $subscription->delete();
        }
    }

    /**
     * Rows that belong to the user rather than to one of their teams.
     */
    protected function deleteUserRecords(User $user): void
    {
        try {
            if ($user->avatar_path) {
                Storage::disk(config('picstome.disk'))->delete($user->avatar_path);
            }
        } catch (Throwable $exception) {
            $this->reportFailure('delete user avatar', ['user' => $user->id], $exception);
        }

        RemoveFromAcumbamailList::dispatch($user->email);

        $user->tokens()->delete();
        ExternalAuthorization::query()->where('user_id', $user->id)->delete();

        DB::table('sessions')->where('user_id', $user->id)->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        $user->delete();
    }

    /**
     * Log external failures without aborting the purge. `report()` keeps them
     * in the application log and in Honeybadger.
     *
     * @param  array<string, mixed>  $context
     */
    protected function reportFailure(string $action, array $context, Throwable $exception): void
    {
        Log::warning("Account deletion could not {$action}.", [
            ...$context,
            'error' => $exception->getMessage(),
        ]);

        report($exception);
    }
}
