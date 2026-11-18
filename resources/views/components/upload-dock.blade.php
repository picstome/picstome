    {{-- Persistent uploader panel. It lives in the layout (not in the gallery
         Livewire component) so uploads and their progress survive an in-app
         navigation. All state is kept in the `uploads` Alpine store. --}}
    <div
        x-show="$store.uploads.open"
        x-cloak
        data-test="upload-dock"
        role="region"
        aria-label="{{ __('Uploads') }}"
        class="fixed inset-x-[3px] bottom-[max(3px,env(safe-area-inset-bottom))] z-[60] sm:inset-x-auto sm:right-[3px] sm:bottom-[3px] sm:w-[26rem]"
    >
        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-2xl dark:border-white/10 dark:bg-brand-night">
            <div class="flex items-start gap-3 px-4 pt-3 pb-2">
                <button
                    type="button"
                    class="min-w-0 flex-1 text-left"
                    x-on:click="$store.uploads.toggleCollapsed()"
                    x-bind:aria-expanded="(! $store.uploads.collapsed).toString()"
                >
                    <span
                        class="block truncate text-base font-semibold text-zinc-900 dark:text-white"
                        x-text="$store.uploads.batchTitle"
                    ></span>
                    <span class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs font-medium text-zinc-500 dark:text-white/60">
                        <span class="tabular-nums" x-text="$store.uploads.percent + '%'"></span>
                        <span aria-hidden="true">·</span>
                        <span class="tabular-nums" x-text="$store.uploads.bytesLabel"></span>
                        <template x-if="$store.uploads.etaLabel !== ''">
                            <span class="flex items-center gap-x-2">
                                <span aria-hidden="true">·</span>
                                <span x-text="$store.uploads.etaLabel"></span>
                            </span>
                        </template>
                    </span>
                </button>

                <div class="flex shrink-0 items-center gap-1">
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800 dark:text-white/60 dark:hover:bg-white/10 dark:hover:text-white"
                        x-on:click="$store.uploads.toggleCollapsed()"
                        x-bind:title="$store.uploads.collapsed ? @js(__('Expand')) : @js(__('Collapse'))"
                        x-bind:aria-label="$store.uploads.collapsed ? @js(__('Expand')) : @js(__('Collapse'))"
                    >
                        <x-icon
                            name="chevron-down"
                            class="size-4 transition-transform duration-200"
                            x-bind:class="$store.uploads.collapsed ? 'rotate-180' : ''"
                        />
                    </button>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800 dark:text-white/60 dark:hover:bg-white/10 dark:hover:text-white"
                        x-on:click="$store.uploads.minimize()"
                        title="{{ __('Minimize') }}"
                        aria-label="{{ __('Minimize') }}"
                        data-test="upload-dock-minimize"
                    >
                        <x-icon name="minus" class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800 dark:text-white/60 dark:hover:bg-white/10 dark:hover:text-white"
                        x-on:click="$store.uploads.requestClose()"
                        title="{{ __('Close') }}"
                        aria-label="{{ __('Close') }}"
                        data-test="upload-dock-close"
                    >
                        <x-icon name="x-mark" class="size-4" />
                    </button>
                </div>
            </div>

            <div class="h-1 w-full bg-zinc-100 dark:bg-white/10">
                <div
                    class="bg-brand-green dark:bg-brand-sage h-full transition-[width] duration-300"
                    x-bind:style="`width: ${$store.uploads.percent}%`"
                ></div>
            </div>

            <div
                x-show="! $store.uploads.collapsed"
                class="max-h-[22rem] overflow-y-auto border-t border-zinc-100 dark:border-white/10"
            >
                {{-- Files rejected before any byte was uploaded. --}}
                <div
                    x-show="$store.uploads.oversized.length > 0"
                    class="border-b border-rose-100 bg-rose-50 px-4 py-2.5 text-xs text-rose-800 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200"
                >
                    <p class="font-medium">{{ __('Files too large') }}</p>
                    <p class="mt-0.5">{{ __('The following files exceed the 5 GB upload limit and were skipped:') }}</p>
                    <ul class="mt-1 list-disc pl-4">
                        <template x-for="(name, index) in $store.uploads.oversized" :key="index">
                            <li x-text="name"></li>
                        </template>
                    </ul>
                </div>

                <div
                    x-show="$store.uploads.unsupported.length > 0"
                    class="border-b border-amber-100 bg-amber-50 px-4 py-2.5 text-xs text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200"
                >
                    <p class="font-medium">{{ __('Unsupported file type') }}</p>
                    <p class="mt-0.5">{{ __('The following files have an unsupported format and were skipped:') }}</p>
                    <ul class="mt-1 list-disc pl-4">
                        <template x-for="(name, index) in $store.uploads.unsupported" :key="index">
                            <li x-text="name"></li>
                        </template>
                    </ul>
                </div>

                {{-- Duplicate names wait for a decision before they upload. --}}
                <div
                    x-show="$store.uploads.entries.some((entry) => entry.status === 'conflict')"
                    class="border-b border-amber-100 bg-amber-50 px-4 py-2.5 text-xs text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200"
                >
                    <p
                        class="font-medium"
                        x-text="$store.uploads.conflictLabel"
                    ></p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            type="button"
                            x-show="$store.uploads.fileVersionsEnabled"
                            class="rounded-md bg-amber-900 px-2.5 py-1 font-medium text-amber-50 transition hover:bg-amber-800"
                            x-on:click="$store.uploads.resolveAllConflicts('version')"
                        >
                            {{ __('Version all') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-md bg-amber-900 px-2.5 py-1 font-medium text-amber-50 transition hover:bg-amber-800"
                            x-on:click="$store.uploads.resolveAllConflicts('replace')"
                        >
                            {{ __('Replace all') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-2.5 py-1 font-medium transition hover:bg-amber-100 dark:hover:bg-white/10"
                            x-on:click="$store.uploads.resolveAllConflicts('skip')"
                        >
                            {{ __('Skip all') }}
                        </button>
                    </div>
                </div>

                <template x-for="entry in $store.uploads.visibleEntries" :key="entry.id">
                    <div class="flex items-start gap-3 border-b border-zinc-100 px-4 pt-0.5 pb-2.5 last:border-0 dark:border-white/5">
                        <div class="mt-0.5 flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-zinc-100 dark:bg-white/10">
                            <img
                                x-show="entry.previewUrl"
                                x-bind:src="entry.previewUrl"
                                alt=""
                                class="size-9 object-cover"
                            />
                            <x-icon
                                x-show="! entry.previewUrl"
                                name="document"
                                class="size-4 text-zinc-400 dark:text-white/50"
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span
                                    class="min-w-0 flex-1 truncate text-sm font-medium text-zinc-800 dark:text-white"
                                    x-text="entry.name"
                                ></span>
                                <span
                                    class="shrink-0 text-xs font-medium text-zinc-500 tabular-nums dark:text-white/60"
                                    x-text="$store.uploads.progressLabel(entry)"
                                ></span>
                            </div>

                            <div class="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs text-zinc-500 dark:text-white/60">
                                <span class="tabular-nums" x-text="$store.uploads.sizeLabel(entry)"></span>
                                <span aria-hidden="true">·</span>
                                <span
                                    x-bind:class="entry.status === 'failed' && 'text-rose-600 dark:text-rose-400'"
                                    x-text="$store.uploads.statusLabel(entry)"
                                ></span>
                            </div>

                            <div
                                x-show="entry.status === 'uploading'"
                                class="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-white/10"
                            >
                                <div
                                    class="bg-brand-green dark:bg-brand-sage h-full transition-[width] duration-300"
                                    x-bind:style="`width: ${entry.progress}%`"
                                ></div>
                            </div>

                            <p
                                x-show="entry.status === 'failed' && entry.error"
                                class="mt-1 text-xs text-rose-600 dark:text-rose-400"
                                x-text="entry.error"
                            ></p>

                            <div x-show="entry.status === 'failed'" class="mt-1.5">
                                <button
                                    type="button"
                                    class="rounded-md bg-zinc-900 px-2.5 py-1 text-xs font-medium text-white transition hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-white/80"
                                    x-on:click="$store.uploads.retry(entry)"
                                >
                                    {{ __('Retry') }}
                                </button>
                            </div>

                            <div x-show="entry.status === 'conflict'" class="mt-1.5 flex flex-wrap gap-1.5">
                                <button
                                    type="button"
                                    x-show="$store.uploads.fileVersionsEnabled"
                                    class="rounded-md bg-zinc-900 px-2.5 py-1 text-xs font-medium text-white transition hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-white/80"
                                    x-on:click="$store.uploads.resolveConflict(entry, 'version')"
                                >
                                    {{ __('Save as new version') }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-800 transition hover:bg-zinc-200 dark:bg-white/10 dark:text-white dark:hover:bg-white/15"
                                    x-on:click="$store.uploads.resolveConflict(entry, 'replace')"
                                >
                                    {{ __('Replace current') }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md px-2.5 py-1 text-xs font-medium text-zinc-600 transition hover:bg-zinc-100 dark:text-white/70 dark:hover:bg-white/10"
                                    x-on:click="$store.uploads.resolveConflict(entry, 'skip')"
                                >
                                    {{ __('Skip') }}
                                </button>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="mt-0.5 shrink-0 rounded p-1 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:text-white/40 dark:hover:bg-white/10 dark:hover:text-white"
                            x-on:click="$store.uploads.remove(entry)"
                            title="{{ __('Remove file') }}"
                            aria-label="{{ __('Remove file') }}"
                        >
                            <x-icon name="x-mark" class="size-3.5" />
                        </button>
                    </div>
                </template>

                <div
                    x-show="$store.uploads.allDone"
                    class="flex items-start gap-2 border-b border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200"
                >
                    <x-icon name="check-circle" class="mt-0.5 size-4 shrink-0" />
                    <div class="min-w-0 flex-1">
                        <p class="font-medium" x-text="$store.uploads.successLabel"></p>
                        <a
                            x-show="$store.uploads.successGallery?.url"
                            x-bind:href="$store.uploads.successGallery?.url"
                            x-on:click="$store.uploads.dismiss()"
                            wire:navigate
                            class="mt-0.5 inline-flex items-center gap-1 text-xs font-semibold underline underline-offset-2 transition hover:text-emerald-950 dark:hover:text-white"
                        >
                            {{ __('View gallery') }}
                            <x-icon name="arrow-right" class="size-3" />
                        </a>
                    </div>
                    <button
                        type="button"
                        class="mt-0.5 shrink-0 rounded p-1 text-emerald-700 transition hover:bg-emerald-100 dark:text-emerald-200 dark:hover:bg-white/10"
                        x-on:click="$store.uploads.dismiss()"
                        title="{{ __('Close') }}"
                        aria-label="{{ __('Close') }}"
                        data-test="upload-dock-dismiss"
                    >
                        <x-icon name="x-mark" class="size-3.5" />
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Small progress chip shown when the panel is hidden by the user. --}}
    <button
        type="button"
        x-show="$store.uploads.showChip"
        x-cloak
        x-on:click="$store.uploads.show()"
        class="fixed right-[3px] bottom-[max(3px,env(safe-area-inset-bottom))] z-[60] flex items-center gap-2 rounded-full border border-zinc-200 bg-white px-3.5 py-2 text-xs font-semibold text-zinc-700 shadow-lg transition hover:bg-zinc-50 dark:border-white/10 dark:bg-brand-night dark:text-white dark:hover:bg-white/10"
        data-test="upload-dock-chip"
    >
        <span class="bg-brand-green dark:bg-brand-sage size-2 animate-pulse rounded-full"></span>
        <span x-text="$store.uploads.batchTitle"></span>
        <span class="tabular-nums text-zinc-500 dark:text-white/60" x-text="$store.uploads.percent + '%'"></span>
    </button>

    {{-- Custom warning for external links. Closing the tab or reloading still
         uses the browser's native beforeunload prompt, whose text the browser
         does not allow us to customize. --}}
    <div
        x-show="$store.uploads.exitPromptUrl !== null"
        x-cloak
        class="fixed inset-0 z-[70] flex items-center justify-center bg-zinc-950/50 p-4 backdrop-blur-sm"
        data-test="upload-leave-prompt"
    >
        <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl dark:bg-zinc-900">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Pending uploads') }}</h2>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('You still have uploads in progress. If you leave this page, they will be cancelled.') }}
            </p>
            <div class="mt-4 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-zinc-600 transition hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/10"
                    x-on:click="$store.uploads.cancelLeave()"
                >
                    {{ __('Keep uploading') }}
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-zinc-900 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-white/80"
                    x-on:click="$store.uploads.leaveAnyway()"
                >
                    {{ __('Leave anyway') }}
                </button>
            </div>
        </div>
    </div>

    {{-- X on the dock cancels queued uploads. Photos already stored stay. --}}
    <div
        x-show="$store.uploads.closePrompt"
        x-cloak
        class="fixed inset-0 z-[75] flex items-center justify-center bg-zinc-950/50 p-4 backdrop-blur-sm"
        data-test="upload-close-prompt"
    >
        <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl dark:bg-zinc-900">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Stop uploading?') }}</h2>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300" x-text="$store.uploads.closeWarning"></p>
            <div class="mt-4 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-zinc-600 transition hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/10"
                    x-on:click="$store.uploads.cancelClose()"
                >
                    {{ __('Keep uploading') }}
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-zinc-900 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-white/80"
                    x-on:click="$store.uploads.confirmClose()"
                >
                    {{ __('Stop and close') }}
                </button>
            </div>
        </div>
    </div>
