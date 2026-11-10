{{--
    Closeup Panel host (the trigger lives in `closeup-panel-button`).

    Everything is inert until the button is pressed: no script beyond the
    Alpine data object (a few hundred bytes, no network) and no image request.
    The detector package, the WASM and the model are imported on the first
    `detect()`, which only runs from the button.

    The panel is a photographer-only culling tool. Only the owner viewer
    includes it: the shared client viewer renders no panel, no trigger and no
    script, so clients never see a score or a face crop.
--}}
@props([
    'photo',
    'imageUrl',
    'fullUrl',
    'originalUrl' => null,
])

@if ($photo->isImage())
    <div class="hidden md:block" data-closeup-wrapper>
        <aside
            data-closeup-panel
            x-data="closeupPanel({
                previewUrl: @js($imageUrl),
                originalUrl: @js($originalUrl),
                imageUrl: @js($imageUrl),
                fullUrl: @js($fullUrl),
            })"
            x-on:closeup-open.window="show()"
            x-on:closeup-close.window="close()"
            x-on:keydown.c.window="toggleByKey($event)"
            x-on:keydown.escape.window="close()"
            x-bind:style="{ display: open ? 'flex' : 'none' }"
            style="display: none"
            class="border-brand-green/10 absolute top-0 right-0 bottom-0 z-30 flex w-80 max-w-full flex-col border-l bg-white/95 shadow-2xl backdrop-blur-md dark:border-white/10 dark:bg-zinc-900/95"
            aria-label="{{ __('Closeup Panel') }}"
            role="complementary"
        >
            {{-- Header: what is being inspected + the close control --}}
            <header class="flex items-center justify-between gap-2 border-b border-zinc-200/80 px-4 py-3 dark:border-white/10">
                <div class="flex min-w-0 items-center gap-2">
                    <span class="bg-brand-green/10 text-brand-green grid size-8 shrink-0 place-items-center rounded-full">
                        <x-icon name="viewfinder-circle" class="size-4" />
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-zinc-800 dark:text-white">{{ __('Closeup') }}</p>
                        <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $photo->name }}</p>
                    </div>
                </div>

                <x-ts-button
                    icon="x-mark"
                    variant="subtle"
                    size="sm"
                    square
                    x-on:click="close()"
                    :aria-label="__('Close Closeup Panel')"
                    data-closeup-close
                />
            </header>

            <div class="relative flex min-h-0 flex-1 flex-col">
                {{-- Loading --}}
                <div
                    x-bind:class="(state === 'loading' || state === 'detecting') ? 'flex' : 'hidden'"
                    class="h-full flex-col items-center justify-center gap-4 px-6 text-center"
                >
                    <div class="bg-brand-green/5 relative grid size-16 place-items-center rounded-2xl">
                        <x-icon name="viewfinder-circle" class="text-brand-green/70 size-6" />
                        <span class="border-brand-green/40 border-t-brand-green absolute inset-0 animate-spin rounded-2xl border-2"></span>
                    </div>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400" x-text="state === 'loading' ? @js(__('Loading photo…')) : @js(__('Detecting faces…'))"></p>
                </div>

                {{-- Idle: never reached automatically, kept for the retry path --}}
                <div
                    x-bind:class="state === 'idle' ? 'flex' : 'hidden'"
                    class="h-full flex-col items-center justify-center gap-4 px-6 text-center"
                >
                    <span class="bg-brand-green/10 text-brand-green grid size-16 place-items-center rounded-2xl">
                        <x-icon name="viewfinder-circle" class="size-7" />
                    </span>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Face zoom and quality rating.') }}</p>
                    <x-ts-button variant="primary" size="sm" icon="sparkles" x-on:click="detect()">
                        {{ __('Detect face') }}
                    </x-ts-button>
                </div>

                {{-- Result: face crop (or the full frame) plus the rating --}}
                <div x-bind:class="['ready', 'no-face', 'error'].includes(state) ? 'flex' : 'hidden'" class="min-h-0 flex-1 flex-col">
                    <div class="relative min-h-0 flex-1 overflow-hidden bg-zinc-950" data-closeup-stage-box>
                        {{-- `contain` never stretches. The crop is shaped like
                             the panel column, and small crops are capped at 2x
                             their real pixels so the browser cannot upscale
                             them into mush. --}}
                        <img
                            class="absolute inset-0 m-auto max-h-full max-w-full object-contain"
                            :src="stageSource"
                            :style="selectedFace ? stageStyle : ''"
                            :alt="selectedFace ? selectedFace.label : @js($photo->name)"
                            role="img"
                            data-closeup-stage
                        />

                        {{-- How many faces were found --}}
                        <div class="pointer-events-none absolute top-3 left-3 flex" x-bind:class="faces.length > 0 ? 'flex' : 'hidden'">
                            <x-ts-badge color="zinc" variant="solid" size="sm">
                                <span x-text="faces.length"></span>&nbsp;<span x-text="faces.length === 1 ? @js(__('face')) : @js(__('faces'))"></span>
                            </x-ts-badge>
                        </div>

                        {{-- No face: honest empty state over the frame --}}
                        <div
                            x-bind:class="state === 'no-face' ? 'flex' : 'hidden'"
                            class="absolute inset-0 items-center justify-center bg-zinc-950/60 p-6 backdrop-blur-sm"
                        >
                            <div class="max-w-56 text-center">
                                <span class="mx-auto mb-3 grid size-12 place-items-center rounded-full bg-white/10 text-white/80">
                                    <x-icon name="user" class="size-6" />
                                </span>
                                <p class="text-sm font-medium text-white">{{ __('No face detected.') }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Multi-face strip: pick which face to inspect --}}
                    <div
                        x-bind:class="faces.length > 1 ? 'flex' : 'hidden'"
                        class="gap-2 overflow-x-auto border-t border-zinc-200/80 bg-white/80 p-2 dark:border-white/10 dark:bg-zinc-900/80"
                    >
                        <template x-for="(face, index) in faces" :key="index">
                            <button
                                type="button"
                                class="size-14 shrink-0 overflow-hidden rounded-lg bg-zinc-900 ring-2 transition"
                                :class="index === selected ? 'ring-brand-green' : 'ring-transparent hover:ring-zinc-400'"
                                :aria-label="face.label"
                                :aria-pressed="index === selected"
                                x-on:click="select(index)"
                            >
                                {{-- object-cover: the square thumbnail crops the
                                     face, it never squashes it. --}}
                                <img class="h-full w-full object-cover" :src="face.thumb" :alt="face.label" />
                            </button>
                        </template>
                    </div>

                    {{-- Footer: rating, bottom-right, click for the breakdown --}}
                    <footer class="border-t border-zinc-200/80 px-4 py-3 dark:border-white/10">
                        <div x-bind:class="state === 'error' ? 'block' : 'hidden'" class="mb-3 rounded-lg bg-red-500/10 px-3 py-2 text-xs text-red-700 dark:text-red-300">
                            <span x-text="error === 'cors-or-load'
                                ? @js(__('Could not load the photo for closeup. Check the CDN settings and try again.'))
                                : @js(__('Face detection is unavailable offline. Rating is shown without eye analysis.'))"></span>
                            <button type="button" class="mt-1 block font-semibold underline" x-on:click="retry()">
                                {{ __('Try again') }}
                            </button>
                        </div>

                        <template x-if="rating">
                            <div>
                                <div class="flex items-end justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-zinc-800 dark:text-white">{{ __('Quality rating') }}</p>
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Sharpness · Exposure · Eyes') }}</p>
                                    </div>

                                    <div class="relative shrink-0">
                                        <button
                                            type="button"
                                            class="flex items-baseline gap-1 rounded-full px-3 py-1.5 text-white shadow-sm transition"
                                            x-bind:class="ratingTone"
                                            x-on:click="showBreakdown = !showBreakdown"
                                            :aria-expanded="showBreakdown"
                                            aria-label="{{ __('Show rating breakdown') }}"
                                            data-closeup-rating
                                        >
                                            <span class="text-base leading-none font-bold" x-text="rating.overall.toFixed(1)"></span>
                                            <span class="text-[0.65rem] leading-none opacity-70">/10</span>
                                        </button>

                                        <div
                                            x-bind:class="showBreakdown ? 'block' : 'hidden'"
                                            class="border-brand-green/10 absolute right-0 bottom-full z-10 mb-3 w-64 rounded-xl border bg-white p-3 text-xs shadow-2xl dark:border-white/10 dark:bg-zinc-800"
                                        >
                                            <dl class="space-y-2">
                                                <div class="flex items-center justify-between gap-2">
                                                    <dt class="text-zinc-600 dark:text-zinc-300">
                                                        {{ __('Sharpness (blur)') }}
                                                        <span class="text-zinc-400" x-text="rating.eyes ? '50%' : '70%'"></span>
                                                    </dt>
                                                    <dd class="font-semibold text-zinc-900 dark:text-white">
                                                        <span x-text="rating.sharp.score.toFixed(1)"></span>/10
                                                    </dd>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <dt class="text-zinc-600 dark:text-zinc-300">
                                                        {{ __('Exposure') }}
                                                        <span class="text-zinc-400" x-text="rating.eyes ? '20%' : '30%'"></span>
                                                    </dt>
                                                    <dd class="font-semibold text-zinc-900 dark:text-white">
                                                        <span x-text="rating.exposure.score.toFixed(1)"></span>/10
                                                    </dd>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <dt class="text-zinc-600 dark:text-zinc-300">
                                                        {{ __('Eyes open') }} <span class="text-zinc-400" x-text="rating.eyes ? '30%' : '0%'"></span>
                                                    </dt>
                                                    <dd class="font-semibold text-zinc-900 dark:text-white" x-text="rating.eyes ? rating.eyes.score.toFixed(1) + '/10' : @js(__('Not measurable'))"></dd>
                                                </div>
                                            </dl>

                                            <div class="border-t border-zinc-200 pt-2 dark:border-white/10">
                                                <p class="text-[0.7rem] leading-relaxed text-zinc-500 dark:text-zinc-400">
                                                    {{ __('A soft photo cannot score high: when sharpness is poor, the final rating is capped no matter how good the light or the eyes are.') }}
                                                </p>
                                                <p x-bind:class="rating.eyes ? 'hidden' : 'block'" class="mt-1 text-[0.7rem] leading-relaxed text-zinc-500 dark:text-zinc-400">
                                                    {{ __('Eyes could not be measured on this photo, so the weights are redistributed (sharpness 70%, exposure 30%). Computed in your browser; nothing is uploaded.') }}
                                                </p>
                                                <p x-bind:class="rating.eyes ? 'block' : 'hidden'" class="text-[0.7rem] leading-relaxed text-zinc-500 dark:text-zinc-400">
                                                    {{ __('Eyes, smile and expression are read by MediaPipe Face Landmarker in your browser. Nothing is uploaded.') }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Free extra data from the same landmark pass. It
                                     never touches the score: it only tells the
                                     photographer what the subject was doing. --}}
                                <div
                                    x-bind:class="rating.signs ? 'flex' : 'hidden'"
                                    class="mt-2 flex-wrap items-center gap-1.5"
                                    data-closeup-expressions
                                >
                                    <x-ts-badge x-show="rating.signs?.smiling" color="lime" size="sm" variant="light">
                                        {{ __('Smiling') }}
                                    </x-ts-badge>
                                    <x-ts-badge x-show="rating.signs?.slightSmile" color="lime" size="sm" variant="light">
                                        {{ __('Slight smile') }}
                                    </x-ts-badge>
                                    <x-ts-badge x-show="rating.signs?.mouthOpen" color="zinc" size="sm" variant="light">
                                        {{ __('Mouth open') }}
                                    </x-ts-badge>
                                    <x-ts-badge x-show="rating.signs?.squinting" color="zinc" size="sm" variant="light">
                                        {{ __('Squinting') }}
                                    </x-ts-badge>
                                    <x-ts-badge x-show="rating.signs?.serious" color="zinc" size="sm" variant="light">
                                        {{ __('Serious') }}
                                    </x-ts-badge>
                                    <x-ts-badge x-show="rating.signs?.browsRaised" color="zinc" size="sm" variant="light">
                                        {{ __('Brows raised') }}
                                    </x-ts-badge>
                                </div>
                            </div>
                        </template>

                        <p x-bind:class="(! rating && state !== 'error') ? 'block' : 'hidden'" class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Rating appears once the face is analysed.') }}
                        </p>

                        {{-- Phase indicator: the fast preview result is already
                             on screen while the untouched original loads, so the
                             sharper crop and score replace it a moment later. --}}
                        <div
                            x-bind:class="loadingOriginal ? 'flex' : 'hidden'"
                            class="mt-2 items-center gap-2 text-[0.7rem] text-zinc-500 dark:text-zinc-400"
                            data-closeup-loading-original
                        >
                            <span class="border-brand-green/30 border-t-brand-green size-3 shrink-0 animate-spin rounded-full border-2"></span>
                            <span>{{ __('Loading from original…') }}</span>
                        </div>
                    </footer>
                </div>
            </div>
        </aside>
        </div>
@endif
