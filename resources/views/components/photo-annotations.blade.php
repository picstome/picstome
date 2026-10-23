@props([
    'photo',
    'comments',
    'canAnnotate' => false,
])

@php
    $currentVersionNumber = $photo->currentVersionNumber();
    $pins = collect($comments)->filter(fn ($comment) => $comment->hasCoordinates());
@endphp

@if ($photo->isImage() && ($canAnnotate || $pins->isNotEmpty()))
    <div
        x-data="{
            annotating: false,
            annotationsVisible: true,
            box: { left: 0, top: 0, width: 0, height: 0 },
            pointerStart: null,
            visibleImage() {
                const container = this.$el.parentElement

                if (!container) {
                    return null
                }

                return Array.from(container.querySelectorAll('img'))
                    .filter((img) => img.naturalWidth > 0)
                    .find((img) => {
                        const rect = img.getBoundingClientRect()

                        return rect.width > 0 && rect.height > 0
                    }) ?? null
            },
            updateBox() {
                if (!this.$el.offsetParent) {
                    return
                }

                const container = this.$el.parentElement
                const image = this.visibleImage()

                if (!container || !image) {
                    this.box = { left: 0, top: 0, width: 0, height: 0 }

                    return
                }

                const containerRect = container.getBoundingClientRect()
                const imageRect = image.getBoundingClientRect()
                const scale = Math.min(
                    imageRect.width / image.naturalWidth,
                    imageRect.height / image.naturalHeight,
                )
                const width = image.naturalWidth * scale
                const height = image.naturalHeight * scale

                this.box = {
                    left: imageRect.left - containerRect.left + (imageRect.width - width) / 2,
                    top: imageRect.top - containerRect.top + (imageRect.height - height) / 2,
                    width,
                    height,
                }
            },
            toggle() {
                this.annotating = !this.annotating

                if (this.annotating && !this.annotationsVisible) {
                    this.annotationsVisible = true
                    this.$dispatch('annotations-visibility-changed', { visible: true })
                }

                this.$dispatch('annotations-toggled', { active: this.annotating })

                if (this.annotating) {
                    this.$nextTick(() => this.updateBox())
                }
            },
            toggleVisibility() {
                this.annotationsVisible = !this.annotationsVisible
                this.$dispatch('annotations-visibility-changed', { visible: this.annotationsVisible })
            },
            startPointer(event) {
                this.pointerStart = { x: event.clientX, y: event.clientY }
            },
            placePin(event) {
                const start = this.pointerStart
                this.pointerStart = null

                if (!this.box.width || !this.box.height) {
                    return
                }

                if (start && Math.hypot(event.clientX - start.x, event.clientY - start.y) > 10) {
                    return
                }

                const rect = event.currentTarget.getBoundingClientRect()
                const x = Math.min(100, Math.max(0, ((event.clientX - rect.left) / rect.width) * 100))
                const y = Math.min(100, Math.max(0, ((event.clientY - rect.top) / rect.height) * 100))

                this.annotating = false
                this.$dispatch('annotations-toggled', { active: false })
                $wire.addAnnotation(Number(x.toFixed(2)), Number(y.toFixed(2)))
            },
        }"
        x-init="updateBox()"
        x-on:toggle-annotations.window="toggle()"
        x-on:toggle-annotations-visibility.window="toggleVisibility()"
        x-on:resize.window.debounce.100ms="updateBox()"
        x-on:load.window.capture.debounce.100ms="updateBox()"
        x-show="typeof zoom === 'undefined' || !zoom"
        class="pointer-events-none absolute inset-0 z-20"
    >
        <div
            x-show="annotating"
            x-cloak
            style="display: none"
            class="pointer-events-auto absolute cursor-crosshair"
            :style="`left: ${box.left}px; top: ${box.top}px; width: ${box.width}px; height: ${box.height}px;`"
            x-on:pointerdown="startPointer($event)"
            x-on:click="placePin($event)"
        ></div>

        @if ($pins->isNotEmpty())
            <div
                x-show="annotationsVisible"
                class="absolute"
                :style="`left: ${box.left}px; top: ${box.top}px; width: ${box.width}px; height: ${box.height}px;`"
            >
                @foreach ($pins as $pin)
                    @php
                        $isOtherVersion = $pin->isFromOtherVersion($currentVersionNumber);
                    @endphp

                    <button
                        type="button"
                        wire:key="annotation-{{ $pin->id }}"
                        @class([
                            'pointer-events-auto absolute flex size-6 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full shadow-lg ring-2 ring-white/80 transition hover:scale-110 focus:outline-none focus-visible:ring-4',
                            'bg-brand-green text-brand-cream' => ! $isOtherVersion,
                            'border-2 border-dashed border-amber-600 bg-amber-400/70 text-amber-900 opacity-80' => $isOtherVersion,
                        ])
                        style="left: {{ $pin->x }}%; top: {{ $pin->y }}%;"
                        title="{{ $isOtherVersion ? __('Annotated on version :number', ['number' => $pin->annotated_version_number]) : __('Photo annotation') }}"
                        x-on:click.stop="$flux.modal('add-comment').show(); $dispatch('focus-comment', { id: {{ $pin->id }} })"
                    >
                        <flux:icon.map-pin variant="micro" class="size-3.5" />
                    </button>
                @endforeach
            </div>
        @endif

        <div x-show="annotating" x-cloak style="display: none" class="pointer-events-none absolute inset-x-0 top-16 flex justify-center">
            <span class="bg-brand-green text-brand-cream rounded-full px-3 py-1 text-xs shadow">
                {{ __('Click on the photo to mark a spot') }}
            </span>
        </div>
    </div>
@endif
