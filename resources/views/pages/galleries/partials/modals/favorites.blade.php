    @if ($this->totalFavorites > 0)
        <x-ts-modal name="favorite-list" class="w-full sm:max-w-lg" x-data="copyToClipboard">
        <div class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Export favorite list') }}</x-ts-heading>
                <x-ts-subheading>
                    {{ __(':count medias in your favorite list.', ['count' => $this->totalFavorites]) }}
                </x-ts-subheading>
            </div>

            <x-tab selected="rater" scroll-on-mobile class="w-full" paddingless shadowless>
<x-tab.items tab="rater" :title="__('App')">
                    <x-ts-text>
                        @if ($this->syncEnabled)
                            {{ __('Rate your favorited images with the Picstome Sync desktop app.') }}
                        @else
                            {{ __('Rate your favorited images with the Picstome Rater desktop app.') }}
                        @endif
                    </x-ts-text>

                    <div class="mt-6 flex items-center gap-2">
                        @php
                            $rateFiles = $this->favoriteNames->map(function ($name) {
                                return pathinfo($name, PATHINFO_FILENAME);
                            })->implode(',');
                        @endphp

                        <x-ts-button :href="'picstome://rate?files=' . $rateFiles . '&rating=1'" variant="primary">
                            {{ __('Rate my images') }}
                        </x-ts-button>
                    </div>

                    <x-ts-text class="mt-2 text-sm">
                        {{ __(':count favorited photos will be rated.', ['count' => $this->totalFavorites]) }}
                    </x-ts-text>

                    <x-ts-text class="mt-1">
                        {{ __("Don't have the app?") }}
                        @if ($this->syncEnabled)
                            <x-ts-link href="https://github.com/picstome/picstome-sync/releases/latest" target="_blank">
                                {{ __('Download Picstome Sync') }}
                            </x-ts-link>
                        @else
                            <x-ts-link href="https://github.com/picstome/picstome-rater/releases/latest" target="_blank">
                                {{ __('Download Picstome Rater') }}
                            </x-ts-link>
                        @endif
                    </x-ts-text>
                </x-tab.items>

                <x-tab.items tab="lightroom" :title="__('Lightroom')">
                    <x-ts-textarea id="lightroom-textarea" readonly rows="4" class="font-mono text-sm">
                        {{
                            implode(', ', $this->favoriteNames->map(function ($name) {
                                return pathinfo($name, PATHINFO_FILENAME);
                            })->toArray())
                        }}

                    </x-ts-textarea>

                    <x-ts-text class="mt-2">
                        {{ __('Paste this filter text to the tool of your choice.') }}
                    </x-ts-text>

                    <div class="mt-6 flex justify-end">
                        <x-ts-button variant="primary" size="sm" x-on:click="navigator.clipboard.writeText(document.getElementById('lightroom-textarea').value)">
                            {{ __('Copy') }}
                        </x-ts-button>
                    </div>
                </x-tab.items>

                <x-tab.items tab="captureone" :title="__('Capture One')">
                    <x-ts-textarea id="captureone-textarea" readonly rows="4" class="font-mono text-sm">
                        {{
                            implode(' ', $this->favoriteNames->map(function ($name) {
                                return pathinfo($name, PATHINFO_FILENAME);
                            })->toArray())
                        }}

                    </x-ts-textarea>

                    <x-ts-text class="mt-2">
                        {{ __('Paste this filter text to the tool of your choice.') }}
                    </x-ts-text>

                    <div class="mt-6 flex justify-end">
                        <x-ts-button variant="primary" size="sm" x-on:click="navigator.clipboard.writeText(document.getElementById('captureone-textarea').value)">
                            {{ __('Copy') }}
                        </x-ts-button>
                    </div>
                </x-tab.items>

                <x-tab.items tab="finder" :title="__('Finder/Explorer')">
                    <x-ts-textarea id="finder-textarea" readonly rows="4" class="font-mono text-sm">
                        {{
                            implode(' OR ', $this->favoriteNames->map(function ($name) {
                                return pathinfo($name, PATHINFO_FILENAME);
                            })->toArray())
                        }}

                    </x-ts-textarea>

                    <x-ts-text class="mt-2">
                        {{ __('Paste this filter text to the tool of your choice.') }}
                    </x-ts-text>

                    <div class="mt-6 flex justify-end">
                        <x-ts-button variant="primary" size="sm" x-on:click="navigator.clipboard.writeText(document.getElementById('finder-textarea').value)">
                            {{ __('Copy') }}
                        </x-ts-button>
                    </div>
                </x-tab.items>

                <x-tab.items tab="list" :title="__('List')">
                    <x-ts-textarea id="list-textarea" readonly rows="auto" class="max-h-96 min-h-26 text-sm">
                        {{ implode(PHP_EOL, $this->favoriteNames->toArray()) }}
                    </x-ts-textarea>

                    <x-ts-text class="mt-2">
                        {{ __('Paste this filter text to the tool of your choice.') }}
                    </x-ts-text>

                    <div class="mt-6 flex justify-end">
                        <x-ts-button variant="primary" size="sm" x-on:click="navigator.clipboard.writeText(document.getElementById('list-textarea').value)">
                            {{ __('Copy') }}
                        </x-ts-button>
                    </div>
                </x-tab.items>
            </x-tab>
        </div>
        </x-ts-modal>
    @endif

    <x-ts-modal name="mark-favorites" class="w-full sm:max-w-lg">
        <form wire:submit="markFavorites" class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Mark favorites') }}</x-ts-heading>
                <x-ts-subheading>
                    {{ __('Paste a list of filenames to mark them as favorites.') }}
                </x-ts-subheading>
            </div>

            <x-ts-textarea
                wire:model="favoriteFileNames"
                :label="__('Filenames')"
                :description:trailing="__('One filename per line.')"
                rows="10"
                class="font-mono text-sm"
            />

            <x-ts-error name="favoriteFileNames" />

            <div class="flex">
                <x-ts-spacer />

                <x-ts-button type="submit" variant="primary">{{ __('Mark as favorites') }}</x-ts-button>
            </div>
        </form>
    </x-ts-modal>
