<?php

use App\Models\Photo;
use App\Services\PhotoUrlService;
use Flux\Flux;
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
     */
    #[Computed]
    public function versions()
    {
        if (! config('picstome.file_versions_enabled')) {
            return collect();
        }

        if ($this->photo->version_count <= 1 && ! $this->photo->current_version_id) {
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
        $currentNumber = $this->photo->currentVersion?->version_number ?? $this->photo->version_count;
        $currentUrls = PhotoUrlService::make($this->photo, $gallery);

        $items = collect([
            [
                'id' => 'current',
                'label' => __('Current').' (v'.$currentNumber.')',
                'source_name' => $this->photo->source_name ?: $this->photo->name,
                'thumbnail_url' => $currentUrls->thumbnail(),
                'image_url' => $currentUrls->original(),
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

        Flux::modal('delete-version-'.$this->photo->id)->show();
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

<div class="absolute bottom-3 left-1/2 z-10 -translate-x-1/2" :class="zoom ? 'hidden' : ''">
    @if ($this->versions->isNotEmpty())
        <flux:modal.trigger name="photo-versions-{{ $photo->id }}">
            <flux:button size="sm" icon="rectangle-stack">
                {{ __('Versions') }} ({{ $this->versions->count() }})
            </flux:button>
        </flux:modal.trigger>

        <flux:modal name="photo-versions-{{ $photo->id }}" class="w-full max-w-6xl">
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
                    $showWatermark = ! $manageable && $photo->gallery->is_share_watermarked && $photo->gallery->team->brand_watermark_url;
                    $watermarkUrl = $photo->gallery->team->brand_watermark_url;
                    $watermarkPosition = $photo->gallery->team->brand_watermark_position;
                    $watermarkOpacity = $photo->gallery->team->brand_watermark_transparency
                        ? (100 - $photo->gallery->team->brand_watermark_transparency) / 100
                        : 1;
                @endphp

                <flux:heading size="lg">{{ __('Compare versions') }}</flux:heading>

                <div class="flex gap-3 overflow-x-auto pb-2">
                    @foreach ($this->versions as $version)
                        <button
                            type="button"
                            class="shrink-0 rounded-lg border p-1 text-center transition"
                            :class="left === '{{ $version['id'] }}' ? 'border-blue-500 ring-2 ring-blue-500/30' : 'border-zinc-200 dark:border-zinc-700'"
                            @click="left = '{{ $version['id'] }}'; scale = 1; x = 0; y = 0"
                        >
                            <img
                                src="{{ $version['thumbnail_url'] }}"
                                alt="{{ $version['label'] }}"
                                class="h-20 w-20 rounded object-cover"
                            />
                            <span class="mt-1 block max-w-20 truncate text-xs">{{ $version['label'] }}</span>
                            <span class="block max-w-20 truncate text-[10px] text-zinc-500">{{ $version['source_name'] }}</span>
                            <span class="block max-w-20 truncate text-[10px] text-zinc-500">{{ $version['uploaded_at_display'] }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <flux:select x-model="left" @change="scale = 1; x = 0; y = 0">
                        @foreach ($this->versions as $version)
                            <flux:select.option value="{{ $version['id'] }}">{{ $version['label'] }} · {{ $version['source_name'] }} · {{ $version['uploaded_at_display'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select x-model="right" @change="scale = 1; x = 0; y = 0">
                        @foreach ($this->versions as $version)
                            <flux:select.option value="{{ $version['id'] }}">{{ $version['label'] }} · {{ $version['source_name'] }} · {{ $version['uploaded_at_display'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="flex items-center gap-2">
                    <flux:button size="sm" @click="scale = Math.min(5, scale * 1.15)">+</flux:button>
                    <flux:button size="sm" @click="scale = Math.max(1, scale / 1.15); if (scale === 1) { x = 0; y = 0; }">-</flux:button>
                    <flux:button size="sm" @click="scale = 1; x = 0; y = 0">{{ __('Reset') }}</flux:button>
                    <span class="text-sm text-zinc-500" x-text="Math.round(scale * 100) + '%'"></span>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="relative min-h-[320px] overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-900" @wheel.prevent="zoom($event)" @mousedown="startPan($event)" @mousemove="pan($event)" @mouseup="stopPan()" @mouseleave="stopPan()">
                        <img :src="activeImage(left)" :alt="leftLabel()" x-ref="leftImage" :style="`transform: translate(${x}px, ${y}px) scale(${scale}); transform-origin: center center;`" class="max-h-[64vh] w-full object-contain transition-transform" loading="lazy" decoding="async" draggable="false" />
                        @if ($showWatermark)
                            @if ($watermarkPosition === 'repeated')
                                <div class="pointer-events-none absolute inset-0 z-10" style="background-image: url('{{ $watermarkUrl }}'); background-repeat: repeat; background-size: 120px; opacity: {{ $watermarkOpacity }};"></div>
                            @else
                                <div @class([
                                    'pointer-events-none absolute inset-0 z-10 flex overflow-hidden',
                                    'items-start justify-center pt-6' => $watermarkPosition === 'top',
                                    'items-end justify-center pb-6' => $watermarkPosition === 'bottom',
                                    'items-center justify-center' => ! in_array($watermarkPosition, ['top', 'bottom']),
                                ])>
                                    <img src="{{ $watermarkUrl }}" alt="" class="max-h-1/2 max-w-1/2 object-contain" style="opacity: {{ $watermarkOpacity }};" />
                                </div>
                            @endif
                        @endif
                    </div>
                    <div class="relative min-h-[320px] overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-900" @wheel.prevent="zoom($event)" @mousedown="startPan($event)" @mousemove="pan($event)" @mouseup="stopPan()" @mouseleave="stopPan()">
                        <img :src="activeImage(right)" :alt="rightLabel()" x-ref="rightImage" :style="`transform: translate(${x}px, ${y}px) scale(${scale}); transform-origin: center center;`" class="max-h-[64vh] w-full object-contain transition-transform" loading="lazy" decoding="async" draggable="false" />
                        @if ($showWatermark)
                            @if ($watermarkPosition === 'repeated')
                                <div class="pointer-events-none absolute inset-0 z-10" style="background-image: url('{{ $watermarkUrl }}'); background-repeat: repeat; background-size: 120px; opacity: {{ $watermarkOpacity }};"></div>
                            @else
                                <div @class([
                                    'pointer-events-none absolute inset-0 z-10 flex overflow-hidden',
                                    'items-start justify-center pt-6' => $watermarkPosition === 'top',
                                    'items-end justify-center pb-6' => $watermarkPosition === 'bottom',
                                    'items-center justify-center' => ! in_array($watermarkPosition, ['top', 'bottom']),
                                ])>
                                    <img src="{{ $watermarkUrl }}" alt="" class="max-h-1/2 max-w-1/2 object-contain" style="opacity: {{ $watermarkOpacity }};" />
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                @if ($manageable)
                    <div class="space-y-2">
                        @foreach ($this->versions as $version)
                            @if (! $version['is_current'])
                                <div class="flex items-center justify-between gap-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                    <div>
                                        <div class="text-sm font-medium">{{ $version['label'] }}</div>
                                        <flux:text class="text-xs">
                                            {{ $version['source_name'] }} · {{ __('Uploaded at :date', ['date' => $version['uploaded_at_display']]) }}
                                        </flux:text>
                                    </div>
                                    <div class="flex gap-2">
                                        <flux:button as="a" size="sm" href="{{ $version['download_url'] }}" target="_blank">
                                            {{ __('Download') }}
                                        </flux:button>
                                        <flux:button size="sm" wire:click="restoreVersion({{ (int) $version['id'] }})">
                                            {{ __('Restore') }}
                                        </flux:button>
                                        <flux:button
                                            size="sm"
                                            variant="primary"
                                            class="btn-coral"
                                            wire:click="requestDeleteVersion({{ (int) $version['id'] }})"
                                        >
                                            {{ __('Delete') }}
                                        </flux:button>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </flux:modal>

        <x-confirm-modal
            name="delete-version-{{ $photo->id }}"
            :title="__('Delete version?')"
            :message="__('This action cannot be undone.')"
            method="deletePendingVersion"
        />
    @endif
</div>
