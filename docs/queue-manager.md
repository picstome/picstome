# Queue manager

Admin-only screen at `/admin/queue` (`admin.queue`) for monitoring the
database queue, retrying failed jobs and checking the scheduled tasks.

## Access

The route uses `auth`, `verified` and `EnsureUserIsAdmin`. The middleware is
also registered as Livewire persistent middleware, so Livewire update requests
keep the admin guard after the initial page load.

## Queue monitoring

The application uses the `database` queue driver, so the screen reads the
framework tables directly:

- `jobs` for pending, reserved and delayed jobs.
- `failed_jobs` for failed jobs.
- `queue.failer` for retrying and forgetting failed jobs.

The worker heartbeat is written to the shared cache by:

- `Queue::looping()` while a `queue:work` daemon is idle.
- `JobProcessing`, `JobProcessed` and `JobFailed` while a job is processed.

`queue:listen` only reports while it is processing a job; production should use
`queue:work` under supervisor/systemd for an accurate idle heartbeat. The cache
store must be shared between the web and worker processes (`CACHE_STORE=database`
in the example environment).

Worker states:

- **Running**: fresh heartbeat.
- **Busy or stopped**: stale heartbeat but reserved jobs exist. A long-running
  job may still be in progress.
- **Stopped**: stale heartbeat and no reserved jobs.
- **Unknown**: no heartbeat recorded yet.

A reserved job is marked **stuck** when it has been reserved longer than the
queue `retry_after` value. Releasing it clears `reserved_at` and makes it
available again. Only release a job when the worker is not actually processing
it; otherwise the job can run twice.

## Actions

- **Retry failed job**: `queue:retry` for a single UUID.
- **Retry all failed**: all failed jobs, or only the selected queue.
- **Forget failed job**: `queue:forget`.
- **Release stuck job**: clears `reserved_at` and sets `available_at` to now.
- **Delete queued job**: removes the row from `jobs`.
- **Restart workers**: `queue:restart`; workers stop after their current job and
  the process manager restarts them.

Every action is re-checked for admin access and logged with the admin ID and
job identifiers.

## Scheduled tasks

The schedule definition lives in `App\Console\ScheduledTasks`, used by both
`routes/console.php` and the admin screen. Daily maintenance tasks are
staggered by 10 minutes starting at 00:00 so they do not all hit the worker at
the same time. Laravel dispatches
`ScheduledTaskStarting`, `ScheduledTaskFinished`, `ScheduledTaskFailed` and
`ScheduledTaskSkipped`; `App\Listeners\ScheduledTaskEventSubscriber` stores the
latest state in `scheduled_tasks` (one row per task, not a run history).

The panel is read-only and shows the last run, status, duration, next run and
the last exception. Tasks that have not run since monitoring was deployed show
**Never**. Running tasks with a very old start time are marked **Stale**.

The scheduler heartbeat is written every minute by the `scheduler-heartbeat`
task and by any other task start. If the scheduler cron or `schedule:work`
process is not running, the panel shows the scheduler as stopped and the task
data stops updating.

Limitations:

- Only scheduled executions are recorded. Running a command manually with
  `php artisan ...` does not update the panel.
- The panel stores the scheduler-level failure (exception/exit code), not the
  command's stdout/stderr.
- Monitoring starts after the migration and the first scheduler tick.

## Configuration

Thresholds are configured in `config/picstome.php` under `queue_manager` and can
be overridden with:

```dotenv
PICSTOME_QUEUE_MANAGER_POLL_SECONDS=5
PICSTOME_QUEUE_MANAGER_PAGE_SIZE=25
PICSTOME_QUEUE_MANAGER_STUCK_AFTER_SECONDS=900
PICSTOME_QUEUE_MANAGER_HEARTBEAT_STALE_AFTER_SECONDS=180
PICSTOME_QUEUE_MANAGER_SCHEDULED_STALE_AFTER_SECONDS=3600
```

## Deployment

1. Run the `create_scheduled_tasks_table` migration.
2. Restart the queue workers so they load the heartbeat listeners and the new
   scheduled task definitions.
3. Confirm the scheduler cron or `schedule:work` process is running.
4. Open `/admin/queue` as an admin and verify the worker and scheduler
   heartbeats.

No queue payload or failed-job data is migrated. The new table only stores
monitoring state.

## Rollback

Drop the `scheduled_tasks` table and remove the route, the navigation entry and
the `ScheduledTasks` registration. Existing queue jobs and failed jobs are not
affected.

## Tests

`resources/views/pages/admin/⚡queue.test.php` covers admin access, the
persistent guard, job listing, stuck detection, retry/forget/release/delete,
worker restart, heartbeat states and the scheduled task lifecycle.
