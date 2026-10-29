<?php

use App\Models\Gallery;
use App\Models\GalleryArchive;
use App\Services\GalleryArchiveService;
use App\Services\GalleryUnlockService;
use App\Services\StorageUsageService;
use App\Traits\InteractsWithTallStackModals;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * Download panel shared by the owner and shared gallery Download tabs.
 *
 * The panel only prepares a ZIP when the user asks for it. Owner-only actions
 * (delete/regenerate) are hidden on the shared page and are also rejected by
 * the component methods.
 */
new class extends Component
{
    use Interactions;
    use InteractsWithTallStackModals;

    public Gallery $gallery;

    public int $count = 0;

    public string $size = '0 B';

    public int $imagesCount = 0;

    public int $videosCount = 0;

    public int $pdfsCount = 0;

    public bool $shared = false;

    public string $variant = 'all';

    public ?int $archiveId = null;

    public ?string $archiveStatus = null;

    public ?string $pendingAction = null;

    public function mount(
        Gallery $gallery,
        int $count = 0,
        string $size = '0 B',
        int $imagesCount = 0,
        int $videosCount = 0,
        int $pdfsCount = 0,
        bool $shared = false,
        string $variant = 'all',
    ): void {
        $this->gallery = $gallery;
        $this->count = $count;
        $this->size = $size;
        $this->imagesCount = $imagesCount;
        $this->videosCount = $videosCount;
        $this->pdfsCount = $pdfsCount;
        $this->shared = $shared;
        $this->variant = $variant === 'favorites' ? 'favorites' : 'all';

        $this->ensureAccess();

        $this->archiveId = $this->latestArchive()?->id;
        $this->archiveStatus = $this->archive?->status;
    }

    #[Computed]
    public function enabled(): bool
    {
        return app(GalleryArchiveService::class)->enabled();
    }

    #[Computed]
    public function archive(): ?GalleryArchive
    {
        if ($this->archiveId === null) {
            return null;
        }

        return GalleryArchive::query()
            ->where('gallery_id', $this->gallery->id)
            ->where('variant', $this->variant)
            ->find($this->archiveId);
    }

    #[Computed]
    public function temporaryStorage(): int
    {
        return StorageUsageService::archivesForGallery($this->gallery);
    }

    #[Computed]
    public function downloadUrl(): ?string
    {
        $archive = $this->archive;

        if (! $archive?->isReady()) {
            return null;
        }

        return $this->shared
            ? route('shares.archives.download', ['gallery' => $this->gallery, 'archive' => $archive])
            : route('galleries.archives.download', ['gallery' => $this->gallery, 'archive' => $archive]);
    }

    public function prepare(): void
    {
        $this->ensureAccess();

        if (! $this->enabled) {
            return;
        }

        if ($this->shared && ! $this->sharedRateLimitAllows()) {
            $this->addError('archive', __('Too many download requests. Please wait a moment.'));

            return;
        }

        try {
            $archive = app(GalleryArchiveService::class)->findOrCreate(
                $this->gallery,
                $this->variant,
                $this->shared ? null : Auth::user(),
            );

            $this->archiveId = $archive->id;
            $this->archiveStatus = $archive->status;
            $this->resetArchiveComputed();
            $this->resetErrorBag('archive');
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('archive', __('The ZIP could not be prepared. Please try again.'));
        }
    }

    public function openPrepareModal(string $action = 'prepare'): void
    {
        $this->ensureAccess();

        if (! $this->enabled) {
            return;
        }

        $this->pendingAction = $action === 'regenerate' ? 'regenerate' : 'prepare';
        $this->openModal('download-confirm');
    }

    public function confirmPrepare(): void
    {
        $action = $this->pendingAction;
        $this->pendingAction = null;
        $this->closeModal('download-confirm');

        if ($action === 'regenerate') {
            $this->regenerate();

            return;
        }

        $this->prepare();
    }

    public function cancelPrepare(): void
    {
        $this->pendingAction = null;
        $this->closeModal('download-confirm');
    }

    public function refreshArchive(): void
    {
        $previousStatus = $this->archiveStatus;
        $archive = $this->latestArchive();

        if (! $archive) {
            return;
        }

        $this->archiveId = $archive->id;
        $this->archiveStatus = $archive->status;
        $this->resetArchiveComputed();

        if (! in_array($previousStatus, ['pending', 'building'], true)) {
            return;
        }

        if ($archive->isReady()) {
            $this->toast()->success(__('The ZIP is ready. You can download it now.'))->send();

            return;
        }

        if ($archive->isFailed()) {
            $this->toast()->error(__('The ZIP could not be prepared. Please try again.'))->send();
        }
    }

    public function delete(): void
    {
        $this->ensureOwner();

        $archive = $this->archive;

        if (! $archive) {
            return;
        }

        if (! app(GalleryArchiveService::class)->delete($archive)) {
            $this->addError('archive', __('The ZIP could not be deleted. Please try again.'));

            return;
        }

        $this->archiveId = null;
        $this->archiveStatus = null;
        $this->resetArchiveComputed();
        $this->toast()->success(__('ZIP deleted.'))->send();
    }

    public function regenerate(): void
    {
        $this->ensureOwner();

        $archive = $this->archive;

        if (! $archive) {
            $this->prepare();

            return;
        }

        try {
            $archive = app(GalleryArchiveService::class)->regenerate($archive, Auth::user());
            $this->archiveId = $archive->id;
            $this->archiveStatus = $archive->status;
            $this->resetArchiveComputed();
            $this->resetErrorBag('archive');
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('archive', __('The ZIP could not be prepared. Please try again.'));
        }
    }

    protected function resetArchiveComputed(): void
    {
        unset($this->archive, $this->downloadUrl, $this->temporaryStorage);
    }

    protected function latestArchive(): ?GalleryArchive
    {
        return GalleryArchive::query()
            ->where('gallery_id', $this->gallery->id)
            ->where('variant', $this->variant)
            ->latest('id')
            ->first();
    }

    protected function ensureAccess(): void
    {
        if ($this->shared) {
            $this->ensureSharedAccess();

            return;
        }

        abort_unless(Auth::check(), 403);

        Gate::authorize('view', $this->gallery);
    }

    protected function ensureOwner(): void
    {
        abort_if($this->shared, 403);

        $this->ensureAccess();
    }

    protected function ensureSharedAccess(): void
    {
        abort_unless($this->gallery->is_shared && $this->gallery->is_share_downloadable, 404);

        if ($this->gallery->share_password && ! app(GalleryUnlockService::class)->isUnlocked($this->gallery)) {
            abort(401);
        }
    }

    protected function sharedRateLimitAllows(): bool
    {
        $key = 'gallery-archive:'.$this->gallery->id.':'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }
};
?>

<div
    @if ($this->archive?->isPending() || $this->archive?->isBuilding())
        wire:poll.2s="refreshArchive"
    @endif
    class="mx-auto max-w-3xl space-y-5 py-8"
>
    <x-ts-callout variant="secondary" icon="information-circle">
        <x-ts-callout-heading>{{ __('Download gallery') }}</x-ts-callout-heading>
        <x-ts-callout-text>
            @if ($shared)
                <p>
                    {{ __('We recommend using a computer to download the gallery, as it may take up a lot of space. You can also download photos individually.') }}
                </p>
            @else
                <div class="space-y-2">
                    <p>
                        {{ __('This ZIP includes processed images, videos and PDFs. It does not include RAW files and only contains the current version of each photo.') }}
                    </p>
                    <p>
                        {{ __('On iPhone or iPad, make sure you have enough free space. The Files app may need extra space to unzip the archive. You can also download photos individually.') }}
                    </p>
                </div>
            @endif
        </x-ts-callout-text>
    </x-ts-callout>

    @if (! $this->enabled)
        <x-ts-callout variant="warning" icon="exclamation-triangle">
            <x-ts-callout-text>
                {{ __('Automatic ZIP preparation is temporarily disabled. You can still download the gallery directly.') }}
            </x-ts-callout-text>
        </x-ts-callout>

        <x-ts-button
            :href="$shared ? route('shares.download', ['gallery' => $gallery]) : route('galleries.download', ['gallery' => $gallery])"
            variant="primary"
            icon="arrow-down-tray"
        >
            {{ __('Download ZIP') }}
        </x-ts-button>
    @else
        @php
            $archive = $this->archive;
            $ready = $archive?->isReady() ?? false;
            $expired = $archive?->isExpired() ?? false;
            $failed = $archive?->isFailed() ?? false;
            $building = $archive?->isBuilding() ?? false;
            $pending = $archive?->isPending() ?? false;
            $total = $ready ? $archive->photos_total : $count;
            $processed = $archive?->photos_processed ?? 0;
            $skipped = $archive?->photos_skipped ?? 0;
            $images = $ready ? $archive->images_count : $imagesCount;
            $videos = $ready ? $archive->videos_count : $videosCount;
            $pdfs = $ready ? $archive->pdfs_count : $pdfsCount;
            $progressTotal = $archive?->photos_total ?: $count;
            $completedItems = $processed + $skipped;
            $packing = $building && $progressTotal > 0 && $completedItems >= $progressTotal;
            $progress = $progressTotal > 0 ? min(100, (int) round($completedItems / $progressTotal * 100)) : 0;
        @endphp

        @if ($shared)
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-white/10">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-x-1.5 text-sm font-semibold">
                        <span>{{ $images }} {{ $images === 1 ? __('image') : __('images') }}</span>
                        <span class="text-zinc-400">·</span>
                        <span>{{ $videos }} {{ $videos === 1 ? __('video') : __('videos') }}</span>
                        <span class="text-zinc-400">·</span>
                        <span>{{ $pdfs }} {{ __('PDF') }}</span>
                    </div>

                    @if ($ready)
                        <x-ts-badge color="green" inset>{{ __('Ready') }}</x-ts-badge>
                    @elseif ($building || $pending)
                        <x-ts-badge color="amber" inset>{{ $pending ? __('Queued') : ($packing ? __('Packing') : __('Preparing')) }}</x-ts-badge>
                    @elseif ($failed)
                        <x-ts-badge color="red" inset>{{ __('Failed') }}</x-ts-badge>
                    @elseif ($expired)
                        <x-ts-badge color="zinc" inset>{{ __('Expired') }}</x-ts-badge>
                    @else
                        <x-ts-badge color="zinc" inset>{{ __('Not generated') }}</x-ts-badge>
                    @endif
                </div>

                <div class="mt-1 text-sm text-zinc-500">
                    {{ $ready ? $archive->formattedSize() : $size }}
                    @if (! $ready)
                        <span>({{ __('approx.') }})</span>
                    @endif
                    @if ($ready && $archive->expires_at)
                        <span>· {{ __('Available until :date', ['date' => $archive->expires_at->translatedFormat('j M Y')]) }}</span>
                    @endif
                </div>

                @if ($ready && $skipped > 0)
                    <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                        {{ __(':count skipped', ['count' => $skipped]) }}
                    </div>
                @endif
            </div>
        @else
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-white/10">
                    <div class="text-xs font-medium text-zinc-500">{{ __('Contents') }}</div>
                    <div class="mt-1 flex flex-wrap items-center gap-x-1.5 text-sm font-semibold">
                        <span>{{ $images }} {{ $images === 1 ? __('image') : __('images') }}</span>
                        <span class="text-zinc-400">·</span>
                        <span>{{ $videos }} {{ $videos === 1 ? __('video') : __('videos') }}</span>
                        <span class="text-zinc-400">·</span>
                        <span>{{ $pdfs }} {{ __('PDF') }}</span>
                    </div>
                    @if ($ready && $skipped > 0)
                        <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                            {{ __(':count skipped', ['count' => $skipped]) }}
                        </div>
                    @endif
                </div>

                <div class="rounded-xl border border-zinc-200 p-4 dark:border-white/10">
                    <div class="text-xs font-medium text-zinc-500">{{ __('Size') }}</div>
                    <div class="mt-1 text-sm font-semibold">
                        {{ $ready ? $archive->formattedSize() : $size }}
                        @if (! $ready)
                            <span class="font-normal text-zinc-500">({{ __('approx.') }})</span>
                        @endif
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-200 p-4 dark:border-white/10">
                    <div class="text-xs font-medium text-zinc-500">{{ __('Status') }}</div>
                    <div class="mt-1">
                        @if ($ready)
                            <x-ts-badge color="green" inset>{{ __('Ready') }}</x-ts-badge>
                        @elseif ($building || $pending)
                            <x-ts-badge color="amber" inset>{{ $pending ? __('Queued') : ($packing ? __('Packing') : __('Preparing')) }}</x-ts-badge>
                        @elseif ($failed)
                            <x-ts-badge color="red" inset>{{ __('Failed') }}</x-ts-badge>
                        @elseif ($expired)
                            <x-ts-badge color="zinc" inset>{{ __('Expired') }}</x-ts-badge>
                        @else
                            <x-ts-badge color="zinc" inset>{{ __('Not generated') }}</x-ts-badge>
                        @endif
                    </div>
                </div>
            </div>

            @if ($archive && ($ready || $expired || $failed))
                <div class="grid gap-2 rounded-xl border border-zinc-200 p-4 text-sm dark:border-white/10 sm:grid-cols-2">
                    <div>
                        <span class="text-zinc-500">{{ __('Generated') }}:</span>
                        {{ $archive->completed_at?->translatedFormat('j M Y, H:i') ?? '—' }}
                    </div>
                    <div>
                        <span class="text-zinc-500">{{ __('Expires') }}:</span>
                        {{ $archive->expires_at?->translatedFormat('j M Y, H:i') ?? '—' }}
                    </div>
                    <div>
                        <span class="text-zinc-500">{{ __('Downloads') }}:</span>
                        {{ $archive->downloads_count }}
                    </div>
                    <div>
                        <span class="text-zinc-500">{{ __('Last download') }}:</span>
                        {{ $archive->last_downloaded_at?->translatedFormat('j M Y, H:i') ?? '—' }}
                    </div>
                </div>
            @endif
        @endif

        @if ($building || $pending)
            <div class="space-y-2">
                @if ($pending)
                    <div class="flex items-center gap-2 text-sm text-zinc-500">
                        <x-icon name="arrow-path" class="size-4 animate-spin" />
                        <span>{{ __('Waiting to start…') }}</span>
                    </div>
                @elseif ($packing)
                    <div class="flex items-center gap-2 text-sm text-zinc-500">
                        <x-icon name="arrow-path" class="size-4 animate-spin" />
                        <span>{{ __('Packing and uploading the ZIP…') }}</span>
                    </div>
                @else
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-500">{{ __('Preparing the ZIP…') }}</span>
                        <span class="font-medium">{{ $completedItems }} / {{ $progressTotal }}</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-white/10">
                        <div class="h-full rounded-full bg-brand-green transition-all dark:bg-brand-sage" style="width: {{ $progress }}%"></div>
                    </div>
                @endif

                <p class="text-xs text-zinc-500">
                    {{ __('You can come back in a few minutes or close the browser and return later; the download will be ready.') }}
                </p>
            </div>
        @endif

        @if ($failed)
            <x-ts-callout variant="danger" icon="x-circle">
                <x-ts-callout-text>
                    {{ $shared ? __('The ZIP could not be prepared. Please try again later.') : ($archive?->failure_reason ?: __('The ZIP could not be prepared.')) }}
                </x-ts-callout-text>
            </x-ts-callout>
        @endif

        @error('archive')
            <x-ts-callout variant="danger" icon="x-circle">
                <x-ts-callout-text>{{ $message }}</x-ts-callout-text>
            </x-ts-callout>
        @enderror

        <div class="flex flex-wrap gap-3">
            @if ($ready)
                <x-ts-button :href="$this->downloadUrl" variant="primary" icon="arrow-down-tray">
                    {{ __('Download ZIP') }}
                </x-ts-button>
            @elseif ($building || $pending)
                <x-ts-button disabled loading variant="primary" icon="arrow-path">
                    {{ $packing ? __('Packing…') : __('Preparing…') }}
                </x-ts-button>
            @else
                <x-ts-button wire:click="openPrepareModal('prepare')" variant="primary" icon="arrow-down-tray">
                    {{ __('Prepare download') }}
                </x-ts-button>
            @endif

            @unless ($shared)
                @if ($ready)
                    <x-ts-button wire:click="openPrepareModal('regenerate')" variant="subtle" icon="arrow-path">
                        {{ __('Regenerate') }}
                    </x-ts-button>

                    <x-ts-button wire:click="delete" variant="ghost" icon="trash" class="text-red-600 dark:text-red-400">
                        {{ __('Delete ZIP') }}
                    </x-ts-button>
                @endif
            @endunless
        </div>

        @unless ($shared)
            <div class="space-y-1 text-xs text-zinc-500">
                <p>
                    {{ __('Temporary ZIPs are stored in your storage and removed after :days days. They do not block uploads.', ['days' => config('picstome.gallery_archives.ttl_days', 7)]) }}
                </p>
                <p>
                    {{ __('Temporary ZIP storage in this gallery: :size', ['size' => $gallery->formatFileSize($this->temporaryStorage)]) }}
                </p>
            </div>
        @endunless
    @endif

    <x-ts-modal name="download-confirm" class="w-full sm:max-w-md">
        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <span class="bg-brand-green/10 text-brand-green dark:bg-brand-sage/15 dark:text-brand-sage flex size-9 shrink-0 items-center justify-center rounded-full">
                    <x-icon name="arrow-down-tray" class="size-5" />
                </span>

                <div>
                    <x-ts-heading size="lg">
                        {{ $pendingAction === 'regenerate' ? __('Regenerate the ZIP?') : __('Prepare the download?') }}
                    </x-ts-heading>

                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        @if ($pendingAction === 'regenerate')
                            {{ __('The current ZIP will be deleted and a new one will be prepared. It may take a few minutes. The ZIP will be available for :days days.', ['days' => config('picstome.gallery_archives.ttl_days', 7)]) }}
                        @elseif ($shared)
                            {{ __('We will prepare a ZIP with the photos, videos and PDFs of the gallery. It may take a few minutes. You can keep browsing and come back later; the ZIP will be available for :days days.', ['days' => config('picstome.gallery_archives.ttl_days', 7)]) }}
                        @else
                            {{ __('We will prepare a ZIP with processed images, videos and PDFs. It may take a few minutes. You can keep browsing and come back later; the ZIP will be available for :days days.', ['days' => config('picstome.gallery_archives.ttl_days', 7)]) }}
                        @endif
                    </p>

                    <p class="mt-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Nothing is generated until you confirm.') }}
                    </p>
                </div>
            </div>

            <div class="flex gap-2">
                <x-ts-spacer />

                <x-ts-modal-close>
                    <x-ts-button variant="outline" wire:click="cancelPrepare">{{ __('Cancel') }}</x-ts-button>
                </x-ts-modal-close>

                <x-ts-modal-close>
                    <x-ts-button variant="primary" wire:click="confirmPrepare">
                        {{ $pendingAction === 'regenerate' ? __('Regenerate ZIP') : __('Prepare download') }}
                    </x-ts-button>
                </x-ts-modal-close>
            </div>
        </div>
    </x-ts-modal>
</div>
