    {{-- The tabs also render when everything is archived: the archived tab is
         the only way back to those photos. --}}
    @if ($this->totalPhotos > 0 || $this->totalArchived > 0)
        {{-- Tabs and filter/sort controls share one sticky row, so the
             controls stay reachable while culling a long wall. --}}
        <div class="bg-brand-paper/95 dark:bg-brand-night/95 sticky top-0 z-20 -mx-6 mt-8 px-6 backdrop-blur-md lg:-mx-8 lg:px-8">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 border-b border-zinc-800/10 pb-1 dark:border-white/20">
                <div class="min-w-0 flex-1">
                    <x-gallery-tabs
                        :favorites-count="$this->totalFavorites"
                        :selection-limit="$gallery->share_selection_limit"
                        :comments-count="$this->totalComments()"
                        :show-sync="$this->syncEnabled"
                        :show-download="true"
                        :show-export="$this->totalFavorites > 0"
                        :archived-count="$this->totalArchived"
                        :active-tab="$activeTab"
                        :favorites-as-filter="true"
                        :favorites-filter-active="$favoritesOnly"
                        :bordered="false"
                    />
                </div>

                <div
                    x-cloak
                    x-show="$wire.activeTab === 'all'"
                    x-transition.opacity.duration.150ms
                    class="shrink-0"
                >
                    <x-gallery-filter-bar
                        :rating="$ratingFilter"
                        :sort-field="$sortField"
                        :sort-direction="$sortDirection"
                        :all-count="$this->filteredPhotoCount"
                        :all-total="$favoritesOnly ? $this->totalFavorites : $this->totalPhotos"
                    />
                </div>
            </div>
        </div>

        <div x-show="$wire.activeTab === 'all'" class="pt-1" data-gallery-panel>
            {{-- Export favorites belongs to the favourites view: the coral button
                 only shows while the Favorites filter is active, so the Photos
                 tab stays clean. The icon next to the Favorites tab is the
                 always-available shortcut to the same modal. --}}
            @if ($favoritesOnly && $this->totalFavorites > 0)
                <div class="mb-3 flex justify-end">
                    <x-ts-modal-trigger name="favorite-list">
                        <x-ts-button
                            size="sm"
                            icon="arrow-path"
                            variant="primary"
                            class="btn-coral"
                            :tooltip="__('Export favorites')"
                            data-export-favorites-content
                        >
                            {{ __('Export favorites') }}
                        </x-ts-button>
                    </x-ts-modal-trigger>
                </div>
            @endif

            @if ($this->filteredPhotoCount > 0)
                <div wire:key="all-photos-{{ $this->queryKey }}">
                    <x-photo-masonry :photos="$this->allPhotos" :$gallery context="owner" html-id-prefix="photo" :view-query="$this->photoQuery->toQuery()" />

                    <x-auto-load-more
                        method="loadMore"
                        :args="['all']"
                        :has-more="$this->filteredPhotoCount > $this->allPhotos->count()"
                    />
                </div>
            @elseif ($favoritesOnly)
                <div class="flex flex-col items-center justify-center py-14" data-filter-empty>
                    <span class="bg-brand-star/10 text-brand-star mb-4 grid size-14 place-items-center rounded-2xl">
                        <x-icon name="heart" class="size-7" />
                    </span>

                    @if ($this->totalFavorites === 0)
                        <x-ts-heading size="lg" level="3">{{ __('No favorites yet') }}</x-ts-heading>
                        <x-ts-text class="mt-2 mb-6 max-w-72 text-center">
                            {{ __('Click the heart on any photo, or add a list of filenames in bulk.') }}
                        </x-ts-text>
                        <x-ts-modal-trigger name="mark-favorites">
                            <x-ts-button variant="primary" icon="heart">{{ __('Add bulk favorites') }}</x-ts-button>
                        </x-ts-modal-trigger>
                    @else
                        <x-ts-heading size="lg" level="3">{{ __('No favorites match these filters') }}</x-ts-heading>
                        <x-ts-text class="mt-2 mb-6 max-w-72 text-center">
                            {{ __('Clear the filters to see every favorited photo.') }}
                        </x-ts-text>
                        <x-ts-button wire:click="clearFilters" variant="primary" icon="x-mark">
                            {{ __('Clear filters') }}
                        </x-ts-button>
                    @endif
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-14" data-filter-empty>
                    <span class="bg-brand-star/10 text-brand-star mb-4 grid size-14 place-items-center rounded-2xl">
                        <x-icon name="funnel" class="size-7" />
                    </span>
                    <x-ts-heading size="lg" level="3">{{ __('No photos match these filters') }}</x-ts-heading>
                    <x-ts-text class="mt-2 mb-6 max-w-72 text-center">
                        {{ __('Try a different rating or clear the filters to see the whole gallery.') }}
                    </x-ts-text>
                    <x-ts-button wire:click="clearFilters" variant="primary" icon="x-mark">
                        {{ __('Clear filters') }}
                    </x-ts-button>
                </div>
            @endif
        </div>
        <div x-show="$wire.activeTab === 'commented'" class="pt-1" data-gallery-panel>
                <x-photo-masonry :photos="$this->commentedPhotos" :$gallery context="owner" html-id-prefix="commented" :as-commented="true" />

                <x-auto-load-more
                    method="loadMore"
                    :args="['commented']"
                    :has-more="$this->commentedIds->count() > $this->commentedPhotos->count()"
                />
            </div>
        @if ($this->totalArchived > 0)
                <div x-show="$wire.activeTab === 'archived'" class="pt-1" data-gallery-panel>
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <x-ts-text class="text-sm">
                            {{ __('Archived photos are hidden from clients and from the gallery download. You can restore them at any time.') }}
                        </x-ts-text>

                        <x-ts-button
                            variant="danger"
                            icon="trash"
                            size="sm"
                            wire:click="requestDeleteArchived"
                            data-delete-archived
                        >
                            {{ __('Delete all archived photos') }}
                        </x-ts-button>
                    </div>

                    <x-photo-masonry :photos="$this->archivedPhotos" :$gallery context="owner" html-id-prefix="archived" :as-archived="true" />

                    <x-auto-load-more
                        method="loadMore"
                        :args="['archived']"
                        :has-more="$this->totalArchived > $this->archivedPhotos->count()"
                    />
                </div>
            @endif
            @if ($this->syncEnabled)
                <div x-show="$wire.activeTab === 'sync'" class="pt-1">
                    <x-gallery-sync-panel :$gallery />
                </div>
            @endif
            <div x-show="$wire.activeTab === 'download'" class="pt-1" data-gallery-panel>
                    <livewire:gallery-download-panel
                        :$gallery
                        :count="$this->totalPhotos"
                        :size="$this->formattedDownloadSize()"
                        :images-count="$this->mediaCounts['images']"
                        :videos-count="$this->mediaCounts['videos']"
                        :pdfs-count="$this->mediaCounts['pdfs']"
                        :key="'download-panel-'.$gallery->id"
                    />
                </div>
    @else
        <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
            <x-icon name="photo" class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
            <x-ts-heading size="lg" level="2">{{ __('No photos') }}</x-ts-heading>
            <x-ts-subheading class="mb-6 max-w-72 text-center">
                {{ __('We couldn\'t find any photos. Add one to get started.') }}
            </x-ts-subheading>
            <div class="flex flex-wrap justify-center gap-3">
                <x-ts-modal-trigger name="add-photos">
                    <x-ts-button variant="primary">
                        {{ __('Add media') }}
                    </x-ts-button>
                </x-ts-modal-trigger>

                @if ($this->syncEnabled)
                    <x-ts-button :href="$gallery->syncDeepLink()" variant="outline" icon="arrow-path">
                        {{ __('Sync this gallery') }}
                    </x-ts-button>
                @endif
            </div>
        </div>
    @endif
