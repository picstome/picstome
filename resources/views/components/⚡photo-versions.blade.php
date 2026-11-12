<?php

use App\Models\Photo;
use App\Services\PhotoUrlService;
use App\Traits\InteractsWithTallStackModals;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Displays the version history for a photo.
 *
 * `manageable` enables owner-only restore/delete/download actions. It is a UI
 * hint only; every action verifies authentication, team ownership and policy
 * again on the server.
 */
new class extends Component
{
    use InteractsWithTallStackModals;

    public Photo $photo;

    public bool $manageable = false;

    public ?int $pendingVersionId = null;

    public function mount(Photo $photo, bool $manageable = false)
    {
        $this->photo = $photo;
        $this->manageable = $manageable;
    }

    /**
     * Build the current + historical version list used by the comparison UI.
     * Query work is skipped entirely when versioning is disabled or absent.
     *
     * Items describe both media types: images carry `image_url`/`thumbnail_url`
     * for the zoom-and-pan comparison, videos carry `video_url` for the
     * synchronized side-by-side player. Families never mix (a video version
     * only exists for a video photo), so the stage picks one branch.
     */
    #[Computed]
    public function versions()
    {
        if (! config('picstome.file_versions_enabled')) {
            return collect();
        }

        // Gate on stored rows, not on `version_count`: the sequence counter
        // stays high after versions are deleted, so it cannot tell whether
        // there is anything left to compare.
        if (! $this->photo->hasStoredVersions()) {
            return collect();
        }

        $history = $this->photo->versions()
            ->select(['id', 'photo_id', 'version_number', 'name', 'source_name', 'path', 'raw_path', 'disk', 'size', 'stored_size', 'status', 'uploaded_at', 'created_at'])
            ->when($this->photo->current_version_id, fn ($query) => $query->where('id', '!=', $this->photo->current_version_id))
            ->orderByDesc('version_number')
            ->get();

        if ($history->isEmpty()) {
            return collect();
        }

        $gallery = $this->photo->gallery;
        $formatDate = fn ($date) => $date?->translatedFormat('j M Y, H:i') ?? '';
        $currentNumber = $this->photo->currentVersionNumber();
        $currentUrls = PhotoUrlService::make($this->photo, $gallery);

        $items = collect([
            [
                'id' => 'current',
                'label' => __('Current').' (v'.$currentNumber.')',
                'source_name' => $this->photo->source_name ?: $this->photo->name,
                'thumbnail_url' => $currentUrls->thumbnail(),
                'image_url' => $currentUrls->original(),
                'video_url' => $currentUrls->video(),
                'is_video' => $this->photo->isVideo(),
                'is_current' => true,
                'uploaded_at_display' => $formatDate($this->photo->uploaded_at ?? $this->photo->created_at),
            ],
        ]);

        foreach ($history as $version) {
            $urls = PhotoUrlService::make($version, $gallery);

            $items->push([
                'id' => (string) $version->id,
                'label' => __('Version :number', ['number' => $version->version_number]),
                'source_name' => $version->source_name ?: $version->name,
                'thumbnail_url' => $urls->thumbnail(),
                'image_url' => $urls->original(),
                'video_url' => $urls->video(),
                'is_video' => $version->isVideo(),
                'download_url' => $this->manageable
                    ? route('galleries.photos.versions.download', ['gallery' => $gallery, 'photo' => $this->photo, 'version' => $version])
                    : null,
                'is_current' => false,
                'version_number' => $version->version_number,
                'uploaded_at_display' => $formatDate($version->uploaded_at ?? $version->created_at),
            ]);
        }

        return $items;
    }

    /**
     * Whether this photo's version history is video, which swaps the image
     * zoom comparison for the synchronized two-player stage.
     */
    #[Computed]
    public function isVideo(): bool
    {
        return $this->photo->isVideo();
    }

    public function restoreVersion(int $versionId)
    {
        $this->ensureCanManage();

        $version = $this->photo->versions()->find($versionId);

        abort_unless($version, 404);

        if ($this->photo->current_version_id === $version->id) {
            return;
        }

        if ($this->photo->path === $version->path) {
            $this->photo->update(['current_version_id' => $version->id]);
            $this->photo->unsetRelation('currentVersion');
            unset($this->versions);

            return;
        }

        if (! $this->photo->current_version_id) {
            $this->photo->snapshotAsVersion($this->photo->version_count ?: 1);
        }

        $this->photo->update([
            'name' => $version->name,
            'source_name' => $version->source_name ?: $version->name,
            'path' => $version->path,
            'raw_path' => $version->raw_path,
            'disk' => $version->diskOrDefault(),
            'size' => $version->size,
            'stored_size' => $version->stored_size ?: $version->size,
            'status' => $version->status,
            'current_version_id' => $version->id,
            'uploaded_at' => $version->uploaded_at,
            // Versions do not store pixel dimensions, so the ones on the photo
            // describe the file being replaced. Clear them instead of showing
            // the wrong resolution in the info panel.
            'width' => null,
            'height' => null,
        ]);

        $this->photo->unsetRelation('currentVersion');
        unset($this->versions);
    }

    /**
     * Deleting a version is confirmed through the modal below, so the
     * deletion only runs once the user confirms it.
     */
    public function requestDeleteVersion(int $versionId): void
    {
        $this->pendingVersionId = $versionId;

        $this->openModal('delete-version-'.$this->photo->id);
    }

    public function deletePendingVersion(): void
    {
        if (! $this->pendingVersionId) {
            return;
        }

        $this->deleteVersion($this->pendingVersionId);

        $this->pendingVersionId = null;
    }

    public function deleteVersion(int $versionId)
    {
        $this->ensureCanManage();

        $version = $this->photo->versions()->find($versionId);

        abort_unless($version, 404);

        abort_if($this->photo->current_version_id === $version->id, 422);
        abort_if(
            $version->path === $this->photo->path
            || $version->path === $this->photo->raw_path
            || $version->raw_path === $this->photo->path,
            422
        );

        $version->deleteFilesImmediately();
        $version->delete();
        unset($this->versions);

        $this->dispatch('version-deleted');
    }

    /**
     * Guard destructive actions independently of the public property value.
     */
    protected function ensureCanManage(): void
    {
        abort_unless(config('picstome.file_versions_enabled'), 403);
        abort_unless($this->manageable, 403);
        abort_unless(Auth::check(), 403);
        abort_unless($this->photo->gallery->team->is(Auth::user()->currentTeam), 403);

        $this->authorize('update', $this->photo);
    }
}; ?>

<div class="contents">
    @if ($this->versions->isNotEmpty())
        <x-ts-modal-trigger name="photo-versions-{{ $photo->id }}">
            <x-dock-button
                icon="rectangle-stack"
                :label="__('Versions (:count)', ['count' => $this->versions->count()])"
                data-photo-versions
            />
        </x-ts-modal-trigger>

        <x-ts-modal name="photo-versions-{{ $photo->id }}" class="w-full max-w-6xl">
            <div
                class="space-y-4"
                x-data="{
                    versions: [],
                    left: 'current',
                    right: 'current',
                    scale: 1,
                    x: 0,
                    y: 0,
                    dragging: false,
                    startX: 0,
                    startY: 0,
                    init() {
                        this.syncVersions();

                        // The comparison stage is a nested Alpine component and
                        // the modal is teleported to <body>, so DOM events from
                        // the selects cannot bubble into it. Dispatching on
                        // window keeps the synced players in step.
                        this.$watch('left', () => this.refreshCompare())
                        this.$watch('right', () => this.refreshCompare())
                    },
                    refreshCompare() {
                        this.$nextTick(() => {
                            window.dispatchEvent(new CustomEvent('video-compare-refresh'))
                        })
                    },
                    syncVersions() {
                        this.versions = JSON.parse(this.$el.dataset.versions || '[]');
                        this.left = this.versions[0]?.id || 'current';
                        this.right = this.versions[1]?.id || this.left;
                        this.scale = 1;
                        this.x = 0;
                        this.y = 0;
                    },
                    find(id) { return this.versions.find(version => String(version.id) === String(id)); },
                    activeImage(id) { return this.find(id)?.image_url || ''; },
                    leftLabel() { return this.find(this.left)?.label || ''; },
                    rightLabel() { return this.find(this.right)?.label || ''; },
                    zoom(event) {
                        const factor = event.deltaY < 0 ? 1.12 : 0.88;
                        this.scale = Math.min(5, Math.max(1, this.scale * factor));
                        if (this.scale === 1) { this.x = 0; this.y = 0; }
                    },
                    startPan(event) { this.dragging = true; this.startX = event.clientX - this.x; this.startY = event.clientY - this.y; },
                    pan(event) { if (this.dragging) { this.x = event.clientX - this.startX; this.y = event.clientY - this.startY; } },
                    stopPan() { this.dragging = false; },
                }"
                data-versions="{{ json_encode($this->versions->values()) }}"
                @version-deleted.window="$nextTick(() => { versions = JSON.parse($el.dataset.versions || '[]'); left = 'current'; right = versions[1]?.id || left; scale = 1; x = 0; y = 0; })"
            >
                @php
                    $showWatermark = ! $manageable
                        && $photo->gallery->is_share_watermarked
                        && $photo->gallery->team->hasBrandWatermark();
                @endphp

                <x-ts-heading size="lg">{{ __('Compare versions') }}</x-ts-heading>

                <div class="flex gap-3 overflow-x-auto pb-2">
                    @foreach ($this->versions as $version)
                        <button
                            type="button"
                            class="shrink-0 rounded-lg border p-1 text-center transition"
                            :class="left === '{{ $version['id'] }}' ? 'border-blue-500 ring-2 ring-blue-500/30' : 'border-zinc-200 dark:border-zinc-700'"
                            @click="left = '{{ $version['id'] }}'; scale = 1; x = 0; y = 0"
                        >
                            @if ($version['is_video'])
                                {{-- Videos have no generated poster frame, so the
                                     tile shows the media type instead of a broken
                                     image request. --}}
                                <span class="flex h-20 w-20 items-center justify-center rounded bg-zinc-200 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                                    <x-icon name="film" class="size-7" />
                                </span>
                            @else
                                <img
                                    src="{{ $version['thumbnail_url'] }}"
                                    alt="{{ $version['label'] }}"
                                    class="h-20 w-20 rounded object-cover"
                                />
                            @endif
                            <span class="mt-1 block max-w-20 truncate text-xs">{{ $version['label'] }}</span>
                            <span class="block max-w-20 truncate text-[10px] text-zinc-500">{{ $version['source_name'] }}</span>
                            <span class="block max-w-20 truncate text-[10px] text-zinc-500">{{ $version['uploaded_at_display'] }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-ts-select x-model="left" @change="scale = 1; x = 0; y = 0">
                        @foreach ($this->versions as $version)
                            <x-ts-select-option value="{{ $version['id'] }}">{{ $version['label'] }} · {{ $version['source_name'] }} · {{ $version['uploaded_at_display'] }}</x-ts-select-option>
                        @endforeach
                    </x-ts-select>
                    <x-ts-select x-model="right" @change="scale = 1; x = 0; y = 0">
                        @foreach ($this->versions as $version)
                            <x-ts-select-option value="{{ $version['id'] }}">{{ $version['label'] }} · {{ $version['source_name'] }} · {{ $version['uploaded_at_display'] }}</x-ts-select-option>
                        @endforeach
                    </x-ts-select>
                </div>

                @if ($this->isVideo)
                    {{-- Video comparison: one control bar drives both players,
                         so the same second of two cuts is on screen at once.
                         The left side owns the audio; the right side is a
                         muted visual reference. Nothing is fetched until the
                         modal opens (see the modal-open listener below). --}}
                    <div
                        x-data="videoCompare({
                            items: () => versions,
                            left: () => left,
                            right: () => right,
                        })"
                        x-on:modal:photo-versions-{{ $photo->id }}-open.window="open()"
                        x-on:modal:photo-versions-{{ $photo->id }}-close.window="close()"
                        data-video-compare
                    >
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="relative overflow-hidden rounded-lg bg-black">
                                <video
                                    x-ref="leftVideo"
                                    x-on:loadedmetadata="onPrimaryLoaded()"
                                    x-on:timeupdate="onPrimaryTimeUpdate()"
                                    x-on:ended="paused = true"
                                    class="max-h-[64vh] w-full object-contain"
                                    playsinline
                                    preload="metadata"
                                    data-video-compare-left
                                ></video>
                                <span class="absolute top-2 left-2 rounded bg-black/60 px-2 py-0.5 text-xs font-medium text-white" x-text="leftItem?.label"></span>
                            </div>
                            <div class="relative overflow-hidden rounded-lg bg-black">
                                <video
                                    x-ref="rightVideo"
                                    x-on:error="onSecondaryError()"
                                    class="max-h-[64vh] w-full object-contain"
                                    playsinline
                                    preload="metadata"
                                    data-video-compare-right
                                ></video>
                                <span class="absolute top-2 left-2 rounded bg-black/60 px-2 py-0.5 text-xs font-medium text-white" x-text="rightItem?.label"></span>
                            </div>
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-3 rounded-lg bg-zinc-100 px-3 py-2 dark:bg-zinc-800" data-video-compare-controls>
                            <button
                                type="button"
                                x-on:click="toggle()"
                                class="bg-brand-green text-brand-cream hover:bg-brand-green-deep grid size-9 place-items-center rounded-full transition"
                                x-bind:aria-label="paused ? @js(__('Play')) : @js(__('Pause'))"
                                data-video-compare-toggle
                            >
                                <x-icon x-show="paused" name="play" variant="solid" class="size-5 translate-x-px" />
                                <x-icon x-show="! paused" name="pause" variant="solid" class="size-5" x-cloak />
                            </button>

                            <input
                                type="range"
                                min="0"
                                step="0.05"
                                x-bind:max="duration || 1"
                                x-bind:value="currentTime"
                                x-on:change="commitDrag($event)"
                                class="min-w-40 flex-1 cursor-pointer accent-brand-green"
                                x-bind:aria-label="@js(__('Seek video'))"
                                data-video-compare-seek
                            />

                            <span class="font-mono text-xs tabular-nums text-zinc-600 dark:text-zinc-300" data-video-compare-time>
                                <span x-text="displayCurrentTime"></span>
                                <span class="text-zinc-400">/</span>
                                <span x-text="displayDuration"></span>
                            </span>

                            <button
                                type="button"
                                x-on:click="toggleMute()"
                                class="grid size-8 place-items-center rounded-full text-zinc-600 transition hover:bg-zinc-200 dark:text-zinc-300 dark:hover:bg-white/10"
                                x-bind:aria-label="muted ? @js(__('Unmute')) : @js(__('Mute'))"
                                data-video-compare-mute
                            >
                                <x-icon x-show="! muted" name="speaker-wave" class="size-4" />
                                <x-icon x-show="muted" name="speaker-x-mark" class="size-4" x-cloak />
                            </button>

                            <button
                                type="button"
                                x-on:click="cycleRate()"
                                class="min-w-11 rounded-full px-2 py-1 text-xs font-semibold tabular-nums text-zinc-600 transition hover:bg-zinc-200 dark:text-zinc-300 dark:hover:bg-white/10"
                                x-bind:aria-label="`@js(__('Playback speed')): ${rate}x`"
                                data-video-compare-rate
                                x-text="rate + 'x'"
                            ></button>
                        </div>

                        <p class="mt-1 text-xs text-zinc-500" x-show="errored">
                            {{ __('The right version could not be loaded. The left one still plays.') }}
                        </p>
                    </div>
                @else
                    <div class="flex items-center gap-2">
                        <x-ts-button size="sm" @click="scale = Math.min(5, scale * 1.15)">+</x-ts-button>
                        <x-ts-button size="sm" @click="scale = Math.max(1, scale / 1.15); if (scale === 1) { x = 0; y = 0; }">-</x-ts-button>
                        <x-ts-button size="sm" @click="scale = 1; x = 0; y = 0">{{ __('Reset') }}</x-ts-button>
                        <span class="text-sm text-zinc-500" x-text="Math.round(scale * 100) + '%'"></span>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="relative min-h-[320px] overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-900" @wheel.prevent="zoom($event)" @mousedown="startPan($event)" @mousemove="pan($event)" @mouseup="stopPan()" @mouseleave="stopPan()">
                            <img :src="activeImage(left)" :alt="leftLabel()" x-ref="leftImage" :style="`transform: translate(${x}px, ${y}px) scale(${scale}); transform-origin: center center;`" class="max-h-[64vh] w-full object-contain transition-transform" loading="lazy" decoding="async" draggable="false" />
                            @if ($showWatermark)
                                <x-watermark :team="$photo->gallery->team" variant="versions" />
                            @endif
                        </div>
                        <div class="relative min-h-[320px] overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-900" @wheel.prevent="zoom($event)" @mousedown="startPan($event)" @mousemove="pan($event)" @mouseup="stopPan()" @mouseleave="stopPan()">
                            <img :src="activeImage(right)" :alt="rightLabel()" x-ref="rightImage" :style="`transform: translate(${x}px, ${y}px) scale(${scale}); transform-origin: center center;`" class="max-h-[64vh] w-full object-contain transition-transform" loading="lazy" decoding="async" draggable="false" />
                            @if ($showWatermark)
                                <x-watermark :team="$photo->gallery->team" variant="versions" />
                            @endif
                        </div>
                    </div>

                @endif

                @if ($manageable)
                    <div class="space-y-2">
                        @foreach ($this->versions as $version)
                            @if (! $version['is_current'])
                                <div class="flex items-center justify-between gap-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                    <div>
                                        <div class="text-sm font-medium">{{ $version['label'] }}</div>
                                        <x-ts-text class="text-xs">
                                            {{ $version['source_name'] }} · {{ __('Uploaded at :date', ['date' => $version['uploaded_at_display']]) }}
                                        </x-ts-text>
                                    </div>
                                    <div class="flex gap-2">
                                        <x-ts-button as="a" size="sm" href="{{ $version['download_url'] }}" target="_blank">
                                            {{ __('Download') }}
                                        </x-ts-button>
                                        <x-ts-button size="sm" wire:click="restoreVersion({{ (int) $version['id'] }})">
                                            {{ __('Restore') }}
                                        </x-ts-button>
                                        <x-ts-button
                                            size="sm"
                                            variant="primary"
                                            class="btn-coral"
                                            wire:click="requestDeleteVersion({{ (int) $version['id'] }})"
                                        >
                                            {{ __('Delete') }}
                                        </x-ts-button>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </x-ts-modal>

        <x-confirm-modal
            name="delete-version-{{ $photo->id }}"
            :title="__('Delete version?')"
            :message="__('This action cannot be undone.')"
            method="deletePendingVersion"
        />

        @if ($this->isVideo)
            @script
                <script>
                    @include('partials.video-player-script')
                </script>
            @endscript
        @endif
    @endif
</div>
