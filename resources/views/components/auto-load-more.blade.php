@props([
    'method' => 'loadMore',
    'args' => [],
    'hasMore' => false,
    'margin' => 1200,
])

@php
    $request = '$wire.'.$method.'('.\Illuminate\Support\Collection::make($args)
        ->map(fn ($argument) => \Illuminate\Support\Js::from($argument))
        ->implode(', ').')';
@endphp

@if ($hasMore)
    <div
        x-data="{
            loading: false,
            done: false,
            load() {
                if (this.loading || this.done || this.$el.getClientRects().length === 0) {
                    return;
                }

                this.loading = true;
                const scrollPosition = window.scrollY;

                Promise.resolve({{ $request }})
                    .then(() => {
                        this.loading = false;

                        this.$nextTick(() => {
                            requestAnimationFrame(() => {
                                requestAnimationFrame(() => {
                                    // The masonry height collapses briefly during
                                    // the Livewire morph, which can clamp the
                                    // scroll to the top. Restore the position
                                    // the visitor had before the update.
                                    if (window.scrollY < scrollPosition - 50) {
                                        window.scrollTo(0, scrollPosition);
                                    }

                                    this.recheck();
                                });
                            });
                        });
                    })
                    .catch(() => {
                        this.loading = false;
                    });
            },
            recheck() {
                if (this.$el.getClientRects().length === 0) {
                    return;
                }

                const rect = this.$el.getBoundingClientRect();

                if (rect.top < window.innerHeight + {{ (int) $margin }}) {
                    this.load();
                }
            },
        }"
        x-init="$nextTick(() => recheck())"
        x-on:scroll.window.throttle.150ms="recheck()"
        x-on:gallery-tab-changed.window="$nextTick(() => recheck())"
        class="mt-6 flex flex-col items-center gap-2"
    >
        <x-ts-text x-show="loading" x-cloak class="text-sm">
            {{ __('Loading more photos...') }}
        </x-ts-text>

        <x-ts-button x-show="!loading" x-on:click="load()" variant="subtle">
            {{ __('Load more photos') }}
        </x-ts-button>
    </div>
@endif
