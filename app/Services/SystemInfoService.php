<?php

namespace App\Services;

use PDO;
use Throwable;

/**
 * Collects read-only runtime and server information for the admin dashboard.
 *
 * Everything here is best-effort: any value that cannot be read (non-Linux
 * hosts, missing git checkout, restricted PDO driver) falls back to null so
 * the page keeps rendering instead of failing.
 */
class SystemInfoService
{
    /**
     * Git branch, commit and the repository root that produced the checkout.
     *
     * Detached HEADs (a CI checkout) report a branch of null, so callers must
     * treat both values as optional. The root is injectable to keep the parsing
     * testable against a fixture directory.
     *
     * @return array{branch: ?string, commit: ?string, root: ?string}
     */
    public function git(?string $root = null): array
    {
        $root ??= base_path();
        $gitDir = $this->resolveGitDir($root);

        if ($gitDir === null) {
            return ['branch' => null, 'commit' => null, 'root' => null];
        }

        $head = $this->readFile($gitDir.'/HEAD');

        if ($head === null) {
            return ['branch' => null, 'commit' => null, 'root' => $root];
        }

        $head = trim($head);

        if (str_starts_with($head, 'ref:')) {
            $ref = trim(substr($head, 4));
            $branch = str_starts_with($ref, 'refs/heads/') ? substr($ref, 11) : $ref;

            return [
                'branch' => $branch,
                'commit' => $this->resolveRef($gitDir, $ref),
                'root' => $root,
            ];
        }

        return [
            'branch' => null,
            'commit' => substr($head, 0, 10) ?: null,
            'root' => $root,
        ];
    }

    /**
     * Framework and runtime versions.
     *
     * @return array{
     *     laravel: string,
     *     php: string,
     *     environment: string,
     *     debug: bool,
     *     timezone: string,
     *     locale: string,
     *     url: string
     * }
     */
    public function application(): array
    {
        return [
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'environment' => app()->environment(),
            'debug' => (bool) config('app.debug'),
            'timezone' => (string) config('app.timezone'),
            'locale' => app()->getLocale(),
            'url' => (string) config('app.url'),
        ];
    }

    /**
     * Storage, cache, queue and database configuration in use.
     *
     * @return array{
     *     database: array{driver: string, version: ?string},
     *     cache: string,
     *     queue: string,
     *     session: string,
     *     filesystem: string
     * }
     */
    public function runtime(): array
    {
        return [
            'database' => [
                'driver' => (string) config('database.default'),
                'version' => $this->databaseVersion(),
            ],
            'cache' => (string) config('cache.default'),
            'queue' => (string) config('queue.default'),
            'session' => (string) config('session.driver'),
            'filesystem' => (string) config('filesystems.default'),
        ];
    }

    /**
     * Host metrics read from the Linux proc filesystem.
     *
     * @return array{
     *     os: ?string,
     *     uptime: ?int,
     *     load: list<float>,
     *     cpus: ?int,
     *     memory: array{total: ?int, used: ?int},
     *     disk: array{total: ?float, used: ?float, free: ?float}
     * }
     */
    public function server(): array
    {
        return [
            'os' => $this->osName(),
            'uptime' => $this->uptime(),
            'load' => $this->loadAverage(),
            'cpus' => $this->cpuCount(),
            'memory' => $this->memory(),
            'disk' => $this->disk(),
        ];
    }

    /**
     * Resolve the real git directory, following the `gitdir:` pointer used by
     * worktrees and submodules.
     */
    protected function resolveGitDir(string $root): ?string
    {
        $path = $root.'/.git';

        if (! is_readable($path)) {
            return null;
        }

        if (is_dir($path)) {
            return $path;
        }

        $contents = $this->readFile($path);

        if ($contents === null || ! preg_match('/^gitdir:\s*(.+)$/m', trim($contents), $matches)) {
            return null;
        }

        $gitDir = trim($matches[1]);

        if (! str_starts_with($gitDir, '/')) {
            $gitDir = $root.'/'.$gitDir;
        }

        return is_dir($gitDir) ? $gitDir : null;
    }

    /**
     * Read a ref from the loose refs, the worktree base or packed-refs.
     */
    protected function resolveRef(string $gitDir, string $ref): ?string
    {
        $base = $this->commonDir($gitDir);

        foreach ([$gitDir.'/'.$ref, $base.'/'.$ref] as $file) {
            $value = $this->readFile($file);

            if ($value !== null && trim($value) !== '') {
                return substr(trim($value), 0, 10);
            }
        }

        $packed = $this->readFile($base.'/packed-refs');

        if ($packed !== null && preg_match('/^([0-9a-f]{40})\s+'.preg_quote($ref, '/').'/m', $packed, $matches)) {
            return substr($matches[1], 0, 10);
        }

        return null;
    }

    /**
     * Shared git directory for worktrees (`commondir` file), if present.
     */
    protected function commonDir(string $gitDir): string
    {
        $common = $this->readFile($gitDir.'/commondir');

        if ($common === null || trim($common) === '') {
            return $gitDir;
        }

        $path = trim($common);

        return str_starts_with($path, '/') ? $path : $gitDir.'/'.$path;
    }

    protected function databaseVersion(): ?string
    {
        try {
            $version = (string) app('db')->connection()->getPdo()
                ->getAttribute(PDO::ATTR_SERVER_VERSION);

            return $version !== '' ? $version : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function osName(): ?string
    {
        $release = $this->readFile('/etc/os-release');

        if ($release === null) {
            return PHP_OS_FAMILY;
        }

        if (preg_match('/^PRETTY_NAME="?([^"\n]+)"?/m', $release, $matches)) {
            return trim($matches[1]);
        }

        return PHP_OS_FAMILY;
    }

    protected function uptime(): ?int
    {
        $contents = $this->readFile('/proc/uptime');

        if ($contents === null) {
            return null;
        }

        return (int) floor((float) explode(' ', trim($contents))[0]);
    }

    /**
     * @return list<float>
     */
    protected function loadAverage(): array
    {
        if (! function_exists('sys_getloadavg')) {
            return [];
        }

        $load = sys_getloadavg();

        if (! is_array($load)) {
            return [];
        }

        return array_map(fn ($value): float => round((float) $value, 2), array_values($load));
    }

    protected function cpuCount(): ?int
    {
        $cpuinfo = $this->readFile('/proc/cpuinfo');

        if ($cpuinfo === null) {
            return null;
        }

        $count = preg_match_all('/^processor\s*:/m', $cpuinfo);

        return $count > 0 ? $count : null;
    }

    /**
     * @return array{total: ?int, used: ?int}
     */
    protected function memory(): array
    {
        $meminfo = $this->readFile('/proc/meminfo');

        if ($meminfo === null) {
            return ['total' => null, 'used' => null];
        }

        preg_match('/^MemTotal:\s+(\d+)\s+kB/m', $meminfo, $total);
        preg_match('/^MemAvailable:\s+(\d+)\s+kB/m', $meminfo, $available);

        if (! isset($total[1])) {
            return ['total' => null, 'used' => null];
        }

        $totalBytes = (int) $total[1] * 1024;
        $availableBytes = isset($available[1]) ? (int) $available[1] * 1024 : null;

        return [
            'total' => $totalBytes,
            'used' => $availableBytes !== null ? $totalBytes - $availableBytes : null,
        ];
    }

    /**
     * @return array{total: ?float, used: ?float, free: ?float}
     */
    protected function disk(): array
    {
        $path = storage_path();

        try {
            $total = disk_total_space($path);
            $free = disk_free_space($path);
        } catch (Throwable) {
            return ['total' => null, 'used' => null, 'free' => null];
        }

        if ($total === false || $free === false) {
            return ['total' => null, 'used' => null, 'free' => null];
        }

        return [
            'total' => (float) $total,
            'used' => (float) ($total - $free),
            'free' => (float) $free,
        ];
    }

    protected function readFile(string $path): ?string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        return $contents === false ? null : $contents;
    }
}
