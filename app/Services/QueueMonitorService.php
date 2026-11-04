<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads the database queue tables and exposes the admin queue manager data.
 *
 * The application uses the database queue driver, so counts and per-job rows
 * come from the framework `jobs` and `failed_jobs` tables. Retrying and
 * forgetting failed jobs delegates to Laravel's queue commands so the admin
 * panel keeps the same behaviour as the CLI.
 */
class QueueMonitorService
{
    public const WORKER_HEARTBEAT_KEY = 'queue-manager:heartbeat:worker';

    public function connectionName(): string
    {
        return (string) config('queue.default', 'database');
    }

    public function driver(): string
    {
        return (string) config('queue.connections.'.$this->connectionName().'.driver', 'sync');
    }

    public function isDatabaseDriver(): bool
    {
        return $this->driver() === 'database';
    }

    public function retryAfter(): int
    {
        return max(1, (int) config('queue.connections.'.$this->connectionName().'.retry_after', 90));
    }

    /**
     * @return array{
     *     driver: string,
     *     connection: string,
     *     retry_after: int,
     *     pending: int,
     *     reserved: int,
     *     stuck_reserved: int,
     *     delayed: int,
     *     failed: int,
     *     failed_last_24h: int,
     *     oldest_pending_at: ?CarbonInterface
     * }
     */
    public function stats(): array
    {
        if (! $this->isDatabaseDriver()) {
            return [
                'driver' => $this->driver(),
                'connection' => $this->connectionName(),
                'retry_after' => $this->retryAfter(),
                'pending' => $this->queueSize(),
                'reserved' => 0,
                'stuck_reserved' => 0,
                'delayed' => 0,
                'failed' => $this->failedCount(),
                'failed_last_24h' => $this->failedCount(last24h: true),
                'oldest_pending_at' => null,
            ];
        }

        $now = now()->timestamp;
        $stuckBefore = $now - $this->retryAfter();

        return [
            'driver' => $this->driver(),
            'connection' => $this->connectionName(),
            'retry_after' => $this->retryAfter(),
            'pending' => $this->jobs()->whereNull('reserved_at')->where('available_at', '<=', $now)->count(),
            'reserved' => $this->jobs()->whereNotNull('reserved_at')->count(),
            'stuck_reserved' => $this->jobs()->whereNotNull('reserved_at')->where('reserved_at', '<', $stuckBefore)->count(),
            'delayed' => $this->jobs()->whereNull('reserved_at')->where('available_at', '>', $now)->count(),
            'failed' => $this->failedCount(),
            'failed_last_24h' => $this->failedCount(last24h: true),
            'oldest_pending_at' => $this->oldestPendingAt(),
        ];
    }

    /**
     * @return list<array{name: string, pending: int, reserved: int, delayed: int, oldest_pending_at: ?CarbonInterface, paused: bool}>
     */
    public function queues(): array
    {
        return $this->queueNames()->map(function (string $queue): array {
            if (! $this->isDatabaseDriver()) {
                return [
                    'name' => $queue,
                    'pending' => $this->queueSize($queue),
                    'reserved' => 0,
                    'delayed' => 0,
                    'oldest_pending_at' => null,
                    'paused' => $this->isPaused($queue),
                ];
            }

            $now = now()->timestamp;
            $query = $this->jobs()->where('queue', $queue);

            return [
                'name' => $queue,
                'pending' => (clone $query)->whereNull('reserved_at')->where('available_at', '<=', $now)->count(),
                'reserved' => (clone $query)->whereNotNull('reserved_at')->count(),
                'delayed' => (clone $query)->whereNull('reserved_at')->where('available_at', '>', $now)->count(),
                'oldest_pending_at' => $this->oldestPendingAt($queue),
                'paused' => $this->isPaused($queue),
            ];
        })->values()->all();
    }

    /**
     * @return Collection<int, array{
     *     id: int,
     *     uuid: ?string,
     *     class: string,
     *     name: string,
     *     queue: string,
     *     attempts: int,
     *     max_tries: mixed,
     *     timeout: mixed,
     *     created_at: CarbonInterface,
     *     available_at: CarbonInterface,
     *     reserved_at: ?CarbonInterface,
     *     age_seconds: int,
     *     reserved_seconds: ?int,
     *     is_delayed: bool,
     *     is_stuck: bool,
     *     is_old: bool,
     *     payload: string
     * }>
     */
    public function queuedJobs(string $queue = '', int $limit = 25): Collection
    {
        if (! $this->isDatabaseDriver()) {
            return collect();
        }

        $now = now()->timestamp;
        $retryAfter = $this->retryAfter();
        $stuckAfter = (int) config('picstome.queue_manager.stuck_after_seconds', 900);

        return $this->jobs()
            ->when($queue !== '', fn ($query) => $query->where('queue', $queue))
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (object $job): array => $this->mapJob($job, $now, $retryAfter, $stuckAfter));
    }

    /**
     * @return Collection<int, array{
     *     uuid: string,
     *     class: string,
     *     name: string,
     *     queue: string,
     *     connection: string,
     *     exception: string,
     *     exception_summary: string,
     *     failed_at: CarbonInterface,
     *     payload: string
     * }>
     */
    public function failedJobs(string $queue = '', int $limit = 25): Collection
    {
        return $this->failed()
            ->when($queue !== '', fn ($query) => $query->where('queue', $queue))
            ->orderByDesc('failed_at')
            ->limit($limit)
            ->get()
            ->map(fn (object $job): array => $this->mapFailedJob($job));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function jobDetails(int $id): ?array
    {
        if (! $this->isDatabaseDriver()) {
            return null;
        }

        $job = $this->jobs()->where('id', $id)->first();

        if (! $job) {
            return null;
        }

        $details = $this->mapJob(
            $job,
            now()->timestamp,
            $this->retryAfter(),
            (int) config('picstome.queue_manager.stuck_after_seconds', 900),
        );

        $details['payload_pretty'] = $this->prettyPayload($job->payload);

        return $details;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function failedJobDetails(string $uuid): ?array
    {
        $job = $this->failed()->where('uuid', $uuid)->first();

        if (! $job) {
            return null;
        }

        $details = $this->mapFailedJob($job);
        $details['exception_full'] = $job->exception;
        $details['payload_pretty'] = $this->prettyPayload($job->payload);

        return $details;
    }

    public function retryFailed(string $uuid): bool
    {
        try {
            $failed = app('queue.failer')->find($uuid);

            if (! $failed) {
                return false;
            }

            Artisan::call('queue:retry', ['id' => [$uuid]]);

            $this->logAction('retry', [
                'uuid' => $uuid,
                'job' => $this->jobClass($this->payload($failed->payload)),
            ]);

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public function retryAllFailed(?string $queue = null): int
    {
        try {
            if ($queue !== null) {
                $ids = $this->failed()
                    ->where('queue', $queue)
                    ->orderByDesc('id')
                    ->pluck('uuid')
                    ->all();

                if ($ids === []) {
                    return 0;
                }

                Artisan::call('queue:retry', ['id' => $ids]);
                $count = count($ids);
            } else {
                $count = $this->failed()->count();

                if ($count === 0) {
                    return 0;
                }

                Artisan::call('queue:retry', ['id' => ['all']]);
            }

            $this->logAction('retry-all', [
                'queue' => $queue,
                'count' => $count,
            ]);

            return $count;
        } catch (Throwable $exception) {
            report($exception);

            return 0;
        }
    }

    public function flushFailed(): int
    {
        try {
            $count = $this->failed()->count();

            if ($count === 0) {
                return 0;
            }

            Artisan::call('queue:flush');
            $this->logAction('flush-failed', ['count' => $count]);

            return $count;
        } catch (Throwable $exception) {
            report($exception);

            return 0;
        }
    }

    public function forgetFailed(string $uuid): bool
    {
        try {
            $forgotten = (bool) app('queue.failer')->forget($uuid);

            if ($forgotten) {
                $this->logAction('forget', ['uuid' => $uuid]);
            }

            return $forgotten;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public function releaseJob(int $id): bool
    {
        if (! $this->isDatabaseDriver()) {
            return false;
        }

        $threshold = now()->timestamp - $this->retryAfter();

        $updated = $this->jobs()
            ->where('id', $id)
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '<', $threshold)
            ->update([
                'reserved_at' => null,
                'available_at' => now()->timestamp,
            ]);

        if ($updated > 0) {
            $this->logAction('release', ['id' => $id]);
        }

        return $updated > 0;
    }

    public function deleteJob(int $id): bool
    {
        if (! $this->isDatabaseDriver()) {
            return false;
        }

        $job = $this->jobs()->where('id', $id)->first();

        if (! $job) {
            return false;
        }

        $deleted = $this->jobs()->where('id', $id)->delete() > 0;

        if ($deleted) {
            $this->logAction('delete', [
                'id' => $id,
                'job' => $this->jobClass($this->payload($job->payload)),
            ]);
        }

        return $deleted;
    }

    public function restartWorkers(): bool
    {
        try {
            Artisan::call('queue:restart');
            $this->logAction('restart-workers');

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * @return array{status: string, last_seen_at: ?CarbonInterface, age_seconds: ?int, connection: ?string, queues: ?string, reserved: int}
     */
    public function workerStatus(): array
    {
        $heartbeat = Cache::get(self::WORKER_HEARTBEAT_KEY);
        $lastSeen = is_array($heartbeat) && isset($heartbeat['at'])
            ? Carbon::createFromTimestamp((int) $heartbeat['at'])
            : null;
        $age = $lastSeen ? max(0, now()->timestamp - $lastSeen->timestamp) : null;
        $staleAfter = (int) config('picstome.queue_manager.heartbeat_stale_after_seconds', 180);
        $reserved = $this->isDatabaseDriver()
            ? $this->jobs()->whereNotNull('reserved_at')->count()
            : 0;

        $status = match (true) {
            $lastSeen === null => 'unknown',
            $age <= $staleAfter => 'running',
            $reserved > 0 => 'busy',
            default => 'stopped',
        };

        return [
            'status' => $status,
            'last_seen_at' => $lastSeen,
            'age_seconds' => $age,
            'connection' => $heartbeat['connection'] ?? null,
            'queues' => $heartbeat['queues'] ?? null,
            'reserved' => $reserved,
        ];
    }

    /**
     * Store a best-effort worker heartbeat. This runs inside queue workers, so
     * it must never throw and interrupt job processing.
     */
    public function recordWorkerHeartbeat(string $connection, string $queue, bool $force = false): void
    {
        try {
            $now = now()->timestamp;
            $heartbeat = Cache::get(self::WORKER_HEARTBEAT_KEY);

            if (! $force && is_array($heartbeat) && (int) ($heartbeat['at'] ?? 0) > $now - 10) {
                return;
            }

            Cache::put(self::WORKER_HEARTBEAT_KEY, [
                'at' => $now,
                'connection' => $connection,
                'queues' => $queue,
                'pid' => getmypid(),
            ], now()->addMinutes(10));
        } catch (Throwable) {
            // Heartbeats are best-effort and must not break the worker.
        }
    }

    private function jobs(): Builder
    {
        $connection = config('queue.connections.'.$this->connectionName().'.connection')
            ?: config('database.default');

        return DB::connection($connection)
            ->table((string) config('queue.connections.'.$this->connectionName().'.table', 'jobs'));
    }

    private function failed(): Builder
    {
        $connection = config('queue.failed.database') ?: config('database.default');

        return DB::connection($connection)
            ->table((string) config('queue.failed.table', 'failed_jobs'));
    }

    /**
     * @return Collection<int, string>
     */
    private function queueNames(): Collection
    {
        $configured = Str::of((string) config('queue.connections.'.$this->connectionName().'.queue', 'default'))
            ->explode(',')
            ->map(fn (string $queue): string => trim($queue))
            ->filter();

        if (! $this->isDatabaseDriver()) {
            return $configured->unique()->values();
        }

        return $configured
            ->merge($this->jobs()->distinct()->pluck('queue'))
            ->merge($this->failed()->distinct()->pluck('queue'))
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    private function failedCount(bool $last24h = false): int
    {
        return $this->failed()
            ->when($last24h, fn ($query) => $query->where('failed_at', '>=', now()->subDay()))
            ->count();
    }

    private function oldestPendingAt(?string $queue = null): ?CarbonInterface
    {
        $createdAt = $this->jobs()
            ->when($queue !== null, fn ($query) => $query->where('queue', $queue))
            ->whereNull('reserved_at')
            ->where('available_at', '<=', now()->timestamp)
            ->min('created_at');

        return $createdAt ? Carbon::createFromTimestamp((int) $createdAt) : null;
    }

    private function queueSize(?string $queue = null): int
    {
        try {
            $queue ??= Str::of((string) config('queue.connections.'.$this->connectionName().'.queue', 'default'))
                ->explode(',')
                ->first();

            return (int) Queue::connection($this->connectionName())->size($queue);
        } catch (Throwable) {
            return 0;
        }
    }

    private function isPaused(string $queue): bool
    {
        try {
            return Queue::isPaused($this->connectionName(), $queue);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mapJob(object $job, int $now, int $retryAfter, int $stuckAfter): array
    {
        $payload = $this->payload($job->payload);
        $createdAt = Carbon::createFromTimestamp((int) $job->created_at);
        $availableAt = Carbon::createFromTimestamp((int) $job->available_at);
        $reservedAt = $job->reserved_at
            ? Carbon::createFromTimestamp((int) $job->reserved_at)
            : null;
        $reservedSeconds = $reservedAt ? max(0, $now - $reservedAt->timestamp) : null;
        $ageSeconds = max(0, $now - $createdAt->timestamp);
        $isDelayed = $availableAt->timestamp > $now;

        return [
            'id' => (int) $job->id,
            'uuid' => $payload['uuid'] ?? null,
            'class' => $this->jobClass($payload),
            'name' => $this->jobName($payload),
            'queue' => (string) $job->queue,
            'attempts' => (int) $job->attempts,
            'max_tries' => $payload['maxTries'] ?? null,
            'timeout' => $payload['timeout'] ?? null,
            'created_at' => $createdAt,
            'available_at' => $availableAt,
            'reserved_at' => $reservedAt,
            'age_seconds' => $ageSeconds,
            'reserved_seconds' => $reservedSeconds,
            'is_delayed' => $isDelayed,
            'is_stuck' => $reservedAt !== null && $reservedSeconds > $retryAfter,
            'is_old' => $reservedAt === null && ! $isDelayed && $ageSeconds > $stuckAfter,
            'payload' => $job->payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapFailedJob(object $job): array
    {
        $payload = $this->payload($job->payload);
        $exception = (string) $job->exception;

        return [
            'uuid' => (string) $job->uuid,
            'class' => $this->jobClass($payload),
            'name' => $this->jobName($payload),
            'queue' => (string) $job->queue,
            'connection' => (string) $job->connection,
            'exception' => $exception,
            'exception_summary' => Str::limit(Str::before($exception, "\n"), 120),
            'failed_at' => Carbon::parse($job->failed_at),
            'payload' => $job->payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $payload): array
    {
        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function jobClass(array $payload): string
    {
        $class = $payload['displayName'] ?? $payload['job'] ?? null;

        return is_string($class) && $class !== '' ? $class : 'Unknown job';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function jobName(array $payload): string
    {
        return Str::afterLast($this->jobClass($payload), '\\');
    }

    private function prettyPayload(string $payload): string
    {
        $decoded = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return Str::limit($payload, 4000);
        }

        $pretty = json_encode(
            $decoded,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return Str::limit($pretty ?: $payload, 4000);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logAction(string $action, array $context = []): void
    {
        Log::info('Queue manager action.', [
            'action' => $action,
            'admin_id' => auth()->id(),
            ...$context,
        ]);
    }
}
