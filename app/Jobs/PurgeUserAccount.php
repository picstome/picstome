<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runs the irreversible purge of a deleted account in the background.
 *
 * A user with tens of thousands of photos cannot be deleted inside the HTTP
 * request that confirms it: the purge walks storage, deletes rows in chunks
 * and talks to Stripe and SFTPGo. The request only revokes access and
 * dispatches this job, so the browser gets an immediate answer.
 *
 * The purge is written to be re-runnable: it always starts from the current
 * database state, so a transient failure (deadlock, storage hiccup) can be
 * retried instead of leaving the account half deleted. Once the user row is
 * gone the next attempt returns immediately.
 */
class PurgeUserAccount implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60];

    /** Long enough for a team with a very large library. */
    public int $timeout = 3600;

    /**
     * Keep only one purge per account: a double click, a retried request or an
     * admin acting twice must not run the purge concurrently.
     */
    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function __construct(public int $userId) {}

    public function handle(AccountDeletionService $deletion): void
    {
        // The user row disappears early in the purge; a retried or duplicated
        // job must not resurrect work against a missing account.
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $deletion->purge($user);
    }

    /**
     * Give up on a broken purge instead of retrying for an hour.
     */
    public function failed(?Throwable $exception): void
    {
        if ($exception !== null) {
            report($exception);
        }
    }
}
