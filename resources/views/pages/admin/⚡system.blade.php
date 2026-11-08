<?php

use App\Services\SystemInfoService;
use App\Traits\FormatsFileSize;
use Livewire\Attributes\Computed;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use FormatsFileSize;
    use Interactions;

    public function mount(): void
    {
        $this->ensureAdmin();
    }

    public function refresh(): void
    {
        $this->ensureAdmin();

        unset($this->application, $this->runtime, $this->server, $this->git);

        $this->toast()->success(__('System info refreshed.'))->send();
    }

    /**
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
    #[Computed]
    public function application(): array
    {
        return app(SystemInfoService::class)->application();
    }

    /**
     * @return array{
     *     database: array{driver: string, version: ?string},
     *     cache: string,
     *     queue: string,
     *     session: string,
     *     filesystem: string
     * }
     */
    #[Computed]
    public function runtime(): array
    {
        return app(SystemInfoService::class)->runtime();
    }

    /**
     * @return array{
     *     os: ?string,
     *     uptime: ?int,
     *     load: list<float>,
     *     cpus: ?int,
     *     memory: array{total: ?int, used: ?int},
     *     disk: array{total: ?float, used: ?float, free: ?float}
     * }
     */
    #[Computed]
    public function server(): array
    {
        return app(SystemInfoService::class)->server();
    }

    /**
     * @return array{branch: ?string, commit: ?string, root: ?string}
     */
    #[Computed]
    public function git(): array
    {
        return app(SystemInfoService::class)->git();
    }

    #[Computed]
    public function uptimeLabel(): string
    {
        $seconds = $this->server['uptime'];

        if ($seconds === null) {
            return __('Unknown');
        }

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($days > 0) {
            return __(':days d :hours h', ['days' => $days, 'hours' => $hours]);
        }

        if ($hours > 0) {
            return __(':hours h :minutes m', ['hours' => $hours, 'minutes' => $minutes]);
        }

        return __(':minutes m', ['minutes' => $minutes]);
    }

    #[Computed]
    public function loadLabel(): string
    {
        if ($this->server['load'] === []) {
            return __('Unavailable');
        }

        return implode(' / ', array_map(
            fn (float $value): string => number_format($value, 2),
            $this->server['load'],
        ));
    }

    #[Computed]
    public function cpuLabel(): string
    {
        return $this->server['cpus'] !== null
            ? (string) $this->server['cpus']
            : __('Unknown');
    }

    #[Computed]
    public function memoryLabel(): string
    {
        ['total' => $total, 'used' => $used] = $this->server['memory'];

        if ($total === null || $used === null) {
            return __('Unknown');
        }

        return __(':used of :total', [
            'used' => $this->formatFileSize($used),
            'total' => $this->formatFileSize($total),
        ]);
    }

    #[Computed]
    public function memoryPercent(): ?int
    {
        ['total' => $total, 'used' => $used] = $this->server['memory'];

        if ($total === null || $used === null || $total <= 0) {
            return null;
        }

        return (int) round($used / $total * 100);
    }

    #[Computed]
    public function diskLabel(): string
    {
        ['total' => $total, 'used' => $used] = $this->server['disk'];

        if ($total === null || $used === null) {
            return __('Unknown');
        }

        return __(':used of :total', [
            'used' => $this->formatFileSize((int) $used),
            'total' => $this->formatFileSize((int) $total),
        ]);
    }

    #[Computed]
    public function diskPercent(): ?int
    {
        ['total' => $total, 'used' => $used] = $this->server['disk'];

        if ($total === null || $used === null || $total <= 0) {
            return null;
        }

        return (int) round($used / $total * 100);
    }

    #[Computed]
    public function branchLabel(): string
    {
        return $this->git['branch'] ?? __('Detached or unknown');
    }

    #[Computed]
    public function environmentLabel(): string
    {
        return $this->application['environment'].($this->application['debug'] ? ' · '.__('debug') : '');
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);
    }
}; ?>

<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <x-ts-heading size="xl" class="text-brand-green dark:text-brand-cream font-semibold">
                {{ __('System info') }}
            </x-ts-heading>
            <x-ts-text class="text-brand-ink/70 dark:text-brand-sage/80 mt-1">
                {{ __('Deployed code, application versions and server health.') }}
            </x-ts-text>
        </div>

        <x-ts-button
            wire:click="refresh"
            icon="arrow-path"
            variant="primary"
            wire:loading.attr="disabled"
            class="btn-ink"
            data-test="refresh-system-info"
        >
            {{ __('Refresh') }}
        </x-ts-button>
    </div>

    <section class="mt-8" aria-labelledby="deployment-heading">
        <div class="mb-4 flex items-center gap-2">
            <x-icon name="code-bracket" class="text-brand-coral size-5" />

            <x-ts-heading
                id="deployment-heading"
                size="lg"
                class="text-brand-green dark:text-brand-cream font-semibold"
            >
                {{ __('Deployment') }}
            </x-ts-heading>
        </div>

        <x-ts-card class="overflow-hidden p-0!" data-test="deployment-card">
            <div class="divide-brand-green/10 dark:divide-white/10 grid divide-y lg:grid-cols-3 lg:divide-x lg:divide-y-0">
                <x-stat-group :title="__('Code')" :description="__('Checked out git revision')" icon="code-bracket" tone="green">
                    <x-stat-row
                        :label="__('Branch')"
                        :value="$this->branchLabel"
                        data-test="branch-row"
                    />

                    <x-stat-row
                        :label="__('Commit')"
                        :value="$this->git['commit'] ?? __('Unknown')"
                        :hint="$this->git['root']"
                    />
                </x-stat-group>

                <x-stat-group :title="__('Framework')" :description="__('Application stack')" icon="cube" tone="coral">
                    <x-stat-row
                        :label="__('Laravel')"
                        :value="$this->application['laravel']"
                        data-test="laravel-row"
                    />

                    <x-stat-row
                        :label="__('PHP')"
                        :value="$this->application['php']"
                    />

                    <x-stat-row
                        :label="__('Environment')"
                        :value="$this->environmentLabel"
                        :hint="__('Locale :locale · :timezone', [
                            'locale' => $this->application['locale'],
                            'timezone' => $this->application['timezone'],
                        ])"
                    />
                </x-stat-group>

                <x-stat-group :title="__('Services')" :description="__('Backing services')" icon="server-stack" tone="sage">
                    <x-stat-row
                        :label="__('Database')"
                        :value="$this->runtime['database']['driver']"
                        :hint="$this->runtime['database']['version']"
                        data-test="database-row"
                    />

                    <x-stat-row
                        :label="__('Cache')"
                        :value="$this->runtime['cache']"
                    />

                    <x-stat-row
                        :label="__('Queue')"
                        :value="$this->runtime['queue']"
                    />

                    <x-stat-row
                        :label="__('Session')"
                        :value="$this->runtime['session']"
                    />

                    <x-stat-row
                        :label="__('Filesystem')"
                        :value="$this->runtime['filesystem']"
                    />
                </x-stat-group>
            </div>
        </x-ts-card>
    </section>

    <section class="mt-8" aria-labelledby="server-heading">
        <div class="mb-4 flex items-center gap-2">
            <x-icon name="server" class="text-brand-coral size-5" />

            <x-ts-heading
                id="server-heading"
                size="lg"
                class="text-brand-green dark:text-brand-cream font-semibold"
            >
                {{ __('Server') }}
            </x-ts-heading>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card
                :label="__('Uptime')"
                :value="$this->uptimeLabel"
                :hint="$this->server['os']"
                data-test="uptime-card"
            />

            <x-stat-card
                :label="__('Load average')"
                :value="$this->loadLabel"
                :hint="__(':count CPUs', ['count' => $this->cpuLabel])"
                data-test="load-card"
            />

            <x-stat-card
                :label="__('Memory')"
                :value="$this->memoryLabel"
                :hint="$this->memoryPercent !== null
                    ? __(':percent% used', ['percent' => $this->memoryPercent])
                    : __('Unknown')"
                data-test="memory-card"
            />

            <x-stat-card
                :label="__('Disk')"
                :value="$this->diskLabel"
                :hint="$this->diskPercent !== null
                    ? __(':percent% used · storage disk', ['percent' => $this->diskPercent])
                    : __('Unknown')"
                data-test="disk-card"
            />
        </div>
    </section>
</div>
