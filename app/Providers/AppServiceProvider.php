<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Listeners\ScheduledTaskEventSubscriber;
use App\Models\Team;
use App\Services\QueueMonitorService;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Livewire\Livewire;
use TallStackUi\Facades\TallStackUi;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Admin translation edits live in storage and are appended after
        // lang/, so they override the repository files at runtime.
        app('translation.loader')->addPath(config('translation_overrides.path'));

        Cashier::useCustomerModel(Team::class);

        // TallStackUI registers its Alpine components from module scripts in
        // the head. Defer Livewire so those modules run before Alpine boots.
        Livewire::useScriptTagAttributes(['defer' => true]);

        // Route middleware only runs on the initial page load. Persist the
        // admin guard so Livewire updates from admin screens stay protected.
        Livewire::addPersistentMiddleware([EnsureUserIsAdmin::class]);

        // Record the last execution of every scheduled task for the admin
        // queue manager. The subscriber is best-effort and never blocks the
        // scheduler if the monitoring table is unavailable.
        Event::subscribe(ScheduledTaskEventSubscriber::class);

        // Queue workers update this heartbeat while idle (daemon loop) and
        // while processing jobs. It must stay best-effort: a cache outage
        // should never stop job processing.
        Queue::looping(function (Looping $event): void {
            app(QueueMonitorService::class)->recordWorkerHeartbeat($event->connectionName, $event->queue);
        });

        Queue::before(function (JobProcessing $event): void {
            app(QueueMonitorService::class)->recordWorkerHeartbeat(
                $event->connectionName,
                $event->job->getQueue(),
                force: true,
            );
        });

        Queue::after(function (JobProcessed $event): void {
            app(QueueMonitorService::class)->recordWorkerHeartbeat(
                $event->connectionName,
                $event->job->getQueue(),
                force: true,
            );
        });

        Queue::failing(function (JobFailed $event): void {
            app(QueueMonitorService::class)->recordWorkerHeartbeat(
                $event->connectionName,
                $event->job->getQueue(),
                force: true,
            );
        });

        // Tabs sit inside existing cards, so they should not add a second shell.
        TallStackUi::customize()
            ->tab()
            ->block('base.body', 'soft-scrollbar flex-nowrap overflow-auto flex bg-transparent dark:bg-transparent rounded-t-lg [&>[role=tab]]:outline-none [&>[role=tab]]:focus-visible:ring-2 [&>[role=tab]]:focus-visible:ring-brand-green/40')
            ->block('item.wrapper', 'inline-flex items-center gap-2 whitespace-nowrap px-3 py-2 text-sm transition-all');

        // Match the legacy callout chrome while keeping TallStackUI color variants.
        TallStackUi::customize()
            ->alert()
            ->block('wrapper', 'p-4 border border-zinc-200 dark:border-white/10');

        // Match the legacy avatar scale (md = 40px, lg = 48px).
        TallStackUi::customize()
            ->avatar()
            ->block('wrapper.sizes.md', 'w-10 h-10 text-base')
            ->block('wrapper.sizes.lg', 'w-12 h-12 text-base')
            ->block('content.image.sizes.md', 'w-10 h-10 text-base')
            ->block('content.image.sizes.lg', 'w-12 h-12 text-base');

        // Disabled inputs stay white and only their text is muted.
        TallStackUi::customize()
            ->form('input')
            ->block('input.color.disabled', 'dark:bg-dark-800 bg-white text-gray-500 dark:text-dark-400');

        // Field labels sit 0.75rem above the control.
        TallStackUi::customize()
            ->form('label')
            ->block('text', 'dark:text-white mb-3 block text-sm font-medium text-zinc-800');

        // Match the application's original button scale.
        TallStackUi::customize()
            ->button()
            ->block('wrapper.sizes.md', 'h-10 text-sm gap-2')
            ->block('wrapper.sizes.sm', 'h-8 text-sm gap-2')
            ->block('wrapper.sizes.xs', 'h-6 text-xs gap-1')
            ->block('wrapper.sizes.lg', 'h-12 text-base gap-2')
            ->block('icon.sizes.md', 'size-4')
            ->block('icon.sizes.sm', 'size-4')
            ->block('icon.sizes.xs', 'size-4')
            ->block('icon.sizes.lg', 'size-5');

        Blade::if('subscribed', function (Team $team) {
            return $team && $team->subscribed();
        });
    }
}
