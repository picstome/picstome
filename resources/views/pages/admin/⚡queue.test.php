<?php

use App\Console\ScheduledTasks;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Jobs\ProcessPhoto;
use App\Models\ScheduledTask;
use App\Models\User;
use App\Services\QueueMonitorService;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    config([
        'picstome.admin_emails' => ['admin@example.com'],
        'queue.default' => 'database',
        'queue.connections.database.connection' => 'sqlite',
        'queue.connections.database.table' => 'jobs',
        'queue.connections.database.retry_after' => 90,
        'queue.failed.database' => 'sqlite',
        'queue.failed.table' => 'failed_jobs',
        'picstome.queue_manager.heartbeat_stale_after_seconds' => 180,
        'picstome.queue_manager.stuck_after_seconds' => 900,
        'picstome.queue_manager.scheduled_task_stale_after_seconds' => 3600,
    ]);

    $this->admin = User::factory()->withPersonalTeam()->create([
        'email' => 'admin@example.com',
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function queueManagerJob(array $overrides = []): int
{
    $payload = [
        'uuid' => (string) Str::uuid(),
        'displayName' => ProcessPhoto::class,
        'job' => 'Illuminate\Queue\CallQueuedHandler@call',
        'maxTries' => 3,
        'maxExceptions' => null,
        'failOnTimeout' => false,
        'backoff' => null,
        'timeout' => 240,
        'retryUntil' => null,
        'data' => ['commandName' => ProcessPhoto::class],
    ];

    return DB::table('jobs')->insertGetId(array_merge([
        'queue' => 'default',
        'payload' => json_encode($payload),
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function queueManagerFailedJob(array $overrides = []): string
{
    $uuid = (string) Str::uuid();
    $payload = [
        'uuid' => $uuid,
        'displayName' => ProcessPhoto::class,
        'job' => 'Illuminate\Queue\CallQueuedHandler@call',
        'maxTries' => 3,
        'timeout' => 240,
        'data' => ['commandName' => ProcessPhoto::class],
    ];

    DB::table('failed_jobs')->insert(array_merge([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode($payload),
        'exception' => "RuntimeException: Boom\n#0 /app/Jobs/ProcessPhoto.php(10)",
        'failed_at' => now(),
    ], $overrides));

    return $uuid;
}

function queueManagerSchedule(): Schedule
{
    $schedule = new Schedule(config('app.timezone'));
    ScheduledTasks::register($schedule);

    return $schedule;
}

it('allows an admin to view the queue manager', function () {
    actingAs($this->admin)
        ->get('/admin/queue')
        ->assertOk()
        ->assertSee('Queue manager')
        ->assertSee('Worker')
        ->assertSee('Scheduled tasks')
        ->assertSee('galleries:delete-expired')
        ->assertSee('Never');
});

it('forbids access to the queue manager for non-admins', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'email' => 'user@example.com',
    ]);

    actingAs($user)->get('/admin/queue')->assertForbidden();
});

it('keeps the admin guard on livewire update requests', function () {
    expect(Livewire::getPersistentMiddleware())->toContain(EnsureUserIsAdmin::class);

    $user = User::factory()->withPersonalTeam()->create([
        'email' => 'user@example.com',
    ]);

    $request = Request::create('/admin/queue', 'POST');
    $request->setUserResolver(fn () => $user);

    expect(fn () => (new EnsureUserIsAdmin)->handle($request, fn () => response('ok')))
        ->toThrow(HttpException::class);
});

it('lists queued and failed jobs', function () {
    queueManagerJob();
    queueManagerFailedJob();

    actingAs($this->admin)
        ->get('/admin/queue')
        ->assertOk()
        ->assertSee('ProcessPhoto');

    Livewire::actingAs($this->admin)
        ->test('pages::admin.queue')
        ->set('tab', 'failed')
        ->assertSee('RuntimeException: Boom');
});

it('marks reserved jobs older than the retry window as stuck', function () {
    queueManagerJob([
        'reserved_at' => now()->subSeconds(120)->timestamp,
        'available_at' => now()->subSeconds(120)->timestamp,
        'created_at' => now()->subSeconds(120)->timestamp,
    ]);

    actingAs($this->admin)
        ->get('/admin/queue')
        ->assertSee('Stuck')
        ->assertSee('Jobs may be stuck');
});

it('retries a failed job', function () {
    $uuid = queueManagerFailedJob();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.queue')
        ->call('confirmRetryFailed', $uuid)
        ->call('executeConfirmed')
        ->assertHasNoErrors();

    expect(DB::table('failed_jobs')->where('uuid', $uuid)->exists())->toBeFalse()
        ->and(DB::table('jobs')->count())->toBe(1);
});

it('retries all failed jobs for the selected queue', function () {
    queueManagerFailedJob();
    queueManagerFailedJob(['queue' => 'gallery-archives']);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.queue')
        ->set('queue', 'default')
        ->call('confirmRetryAllFailed')
        ->call('executeConfirmed')
        ->assertHasNoErrors();

    expect(DB::table('failed_jobs')->where('queue', 'default')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->where('queue', 'gallery-archives')->count())->toBe(1)
        ->and(DB::table('jobs')->count())->toBe(1);
});

it('forgets a failed job', function () {
    $uuid = queueManagerFailedJob();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.queue')
        ->call('confirmForgetFailed', $uuid)
        ->call('executeConfirmed')
        ->assertHasNoErrors();

    expect(DB::table('failed_jobs')->where('uuid', $uuid)->exists())->toBeFalse();
});

it('deletes all failed jobs', function () {
    queueManagerFailedJob();
    queueManagerFailedJob();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.queue')
        ->call('confirmFlushFailed')
        ->assertSee('This permanently deletes all 2 failed jobs')
        ->call('executeConfirmed')
        ->assertHasNoErrors();

    expect(DB::table('failed_jobs')->count())->toBe(0);
});

it('releases a stuck reserved job', function () {
    $id = queueManagerJob([
        'reserved_at' => now()->subSeconds(120)->timestamp,
        'available_at' => now()->subSeconds(120)->timestamp,
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.queue')
        ->call('confirmReleaseJob', $id)
        ->call('executeConfirmed')
        ->assertHasNoErrors();

    $job = DB::table('jobs')->where('id', $id)->first();

    expect($job->reserved_at)->toBeNull()
        ->and((int) $job->available_at)->toBeGreaterThanOrEqual(now()->subSeconds(5)->timestamp);
});

it('does not release a job that is not stuck', function () {
    $id = queueManagerJob([
        'reserved_at' => now()->timestamp,
        'available_at' => now()->timestamp,
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.queue')
        ->call('confirmReleaseJob', $id)
        ->call('executeConfirmed')
        ->assertHasNoErrors();

    expect(DB::table('jobs')->where('id', $id)->value('reserved_at'))->not->toBeNull();
});

it('deletes a queued job', function () {
    $id = queueManagerJob();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.queue')
        ->call('confirmDeleteJob', $id)
        ->call('executeConfirmed')
        ->assertHasNoErrors();

    expect(DB::table('jobs')->where('id', $id)->exists())->toBeFalse();
});

it('sends the worker restart signal', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.queue')
        ->call('confirmRestartWorkers')
        ->call('executeConfirmed')
        ->assertHasNoErrors();

    expect(Cache::has('illuminate:queue:restart'))->toBeTrue();
});

it('shows a running worker from a fresh heartbeat', function () {
    Cache::put(QueueMonitorService::WORKER_HEARTBEAT_KEY, [
        'at' => now()->timestamp,
        'connection' => 'database',
        'queues' => 'default',
    ], now()->addMinutes(10));

    actingAs($this->admin)
        ->get('/admin/queue')
        ->assertSee('Running')
        ->assertDontSee('Queue worker is not running');
});

it('shows a stopped worker when the heartbeat is stale and no jobs are reserved', function () {
    Cache::put(QueueMonitorService::WORKER_HEARTBEAT_KEY, [
        'at' => now()->subMinutes(10)->timestamp,
        'connection' => 'database',
        'queues' => 'default',
    ], now()->addMinutes(10));

    actingAs($this->admin)
        ->get('/admin/queue')
        ->assertSee('Stopped')
        ->assertSee('Queue worker is not running');
});

it('shows a busy worker when the heartbeat is stale but jobs are reserved', function () {
    Cache::put(QueueMonitorService::WORKER_HEARTBEAT_KEY, [
        'at' => now()->subMinutes(10)->timestamp,
        'connection' => 'database',
        'queues' => 'default',
    ], now()->addMinutes(10));

    queueManagerJob([
        'reserved_at' => now()->timestamp,
        'available_at' => now()->timestamp,
    ]);

    actingAs($this->admin)
        ->get('/admin/queue')
        ->assertSee('Busy or stopped')
        ->assertSee('Worker heartbeat is stale');
});

it('records the scheduled task lifecycle', function () {
    $event = queueManagerSchedule()->command('test:command')->daily()->name('test:command');

    event(new ScheduledTaskStarting($event));

    $task = ScheduledTask::query()->where('name', 'test:command')->firstOrFail();

    expect($task->last_status)->toBe(ScheduledTask::STATUS_RUNNING)
        ->and($task->last_started_at)->not->toBeNull()
        ->and(Cache::has(ScheduledTasks::HEARTBEAT_CACHE_KEY))->toBeTrue();

    event(new ScheduledTaskFinished($event, 1.25));

    $task->refresh();

    expect($task->last_status)->toBe(ScheduledTask::STATUS_SUCCESS)
        ->and($task->last_runtime)->toBe(1.25)
        ->and($task->last_finished_at)->not->toBeNull();

    event(new ScheduledTaskFailed($event, new RuntimeException('Boom')));

    $task->refresh();

    expect($task->last_status)->toBe(ScheduledTask::STATUS_FAILED)
        ->and($task->last_exception)->toContain('Boom');

    event(new ScheduledTaskSkipped($event));

    $task->refresh();

    expect($task->last_status)->toBe(ScheduledTask::STATUS_SKIPPED)
        ->and($task->last_skipped_at)->not->toBeNull();
});

it('lists scheduled tasks with their last run', function () {
    ScheduledTask::query()->create([
        'name' => 'galleries:delete-expired',
        'command' => 'galleries:delete-expired',
        'expression' => '0 0 * * *',
        'last_status' => ScheduledTask::STATUS_SUCCESS,
        'last_started_at' => now()->subMinutes(2),
        'last_finished_at' => now()->subMinute(),
        'last_runtime' => 1.5,
    ]);

    actingAs($this->admin)
        ->get('/admin/queue')
        ->assertSee('galleries:delete-expired')
        ->assertSee('OK')
        ->assertSee('1.5 s')
        ->assertSee('Daily at 00:10')
        ->assertSee('php artisan gallery-archives:prune --force')
        ->assertDontSee('scheduler-heartbeat');
});

it('shows the last error of a failed scheduled task', function () {
    ScheduledTask::query()->create([
        'name' => 'temp:cleanup',
        'command' => 'temp:cleanup --force',
        'expression' => '0 0 * * *',
        'last_status' => ScheduledTask::STATUS_FAILED,
        'last_started_at' => now()->subMinutes(2),
        'last_finished_at' => now()->subMinute(),
        'last_runtime' => 0.3,
        'last_exception' => 'RuntimeException: Scheduled cleanup failed',
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.queue')
        ->call('showScheduledTask', 'temp:cleanup')
        ->assertSee('Scheduled cleanup failed')
        ->assertSee('Failed');
});

it('renders the queue manager in the browser', function () {
    $this->actingAs($this->admin);

    visit('/admin/queue')
        ->assertSee('Queue manager')
        ->assertSee('Scheduled tasks')
        ->assertSee('galleries:delete-expired')
        ->assertNoJavascriptErrors();
})->group('browser');

it('switches between queue tabs in the browser', function () {
    queueManagerFailedJob();

    $this->actingAs($this->admin);

    $page = visit('/admin/queue');

    $page->assertSee('Queue manager')
        ->click('@queue-tab-failed')
        ->assertSee('RuntimeException: Boom')
        ->click('@queue-tab-pending')
        ->assertSee('No queued jobs.')
        ->assertNoJavascriptErrors();

    $tabCount = $page->page()->evaluate(<<<'JS'
        () => document.querySelectorAll('[data-test="queue-tab-pending"]').length
    JS);

    expect($tabCount)->toBe(1);
})->group('browser');

it('opens failed job details in the browser', function () {
    queueManagerFailedJob();

    $this->actingAs($this->admin);

    visit('/admin/queue')
        ->click('@queue-tab-failed')
        ->click('@failed-job-details')
        ->assertSee('Payload')
        ->assertSee('UUID')
        ->assertNoJavascriptErrors();
})->group('browser');

it('opens the failed job retry confirmation in the browser', function () {
    queueManagerFailedJob();

    $this->actingAs($this->admin);

    visit('/admin/queue')
        ->click('@queue-tab-failed')
        ->click('@failed-job-retry')
        ->assertSee('Retry failed job?')
        ->assertSee('The job will be pushed back onto the queue')
        ->assertNoJavascriptErrors();
})->group('browser');

it('opens the delete-all-failed confirmation in the browser', function () {
    queueManagerFailedJob();
    queueManagerFailedJob();

    $this->actingAs($this->admin);

    visit('/admin/queue')
        ->click('@queue-tab-failed')
        ->click('@delete-all-failed')
        ->assertSee('Delete all failed jobs?')
        ->assertSee('This permanently deletes all 2 failed jobs')
        ->assertNoJavascriptErrors();
})->group('browser');

it('opens scheduled task details in the browser', function () {
    ScheduledTask::query()->create([
        'name' => 'temp:cleanup',
        'command' => 'temp:cleanup --force',
        'expression' => '0 0 * * *',
        'last_status' => ScheduledTask::STATUS_FAILED,
        'last_started_at' => now()->subMinutes(2),
        'last_finished_at' => now()->subMinute(),
        'last_runtime' => 0.3,
        'last_exception' => 'RuntimeException: Scheduled cleanup failed',
    ]);

    $this->actingAs($this->admin);

    visit('/admin/queue')
        ->click('@auto-refresh-toggle')
        ->click('@scheduled-task-details-tempcleanup')
        ->assertSee('Scheduled cleanup failed')
        ->assertSee('Last error')
        ->assertNoJavascriptErrors();
})->group('browser');
