<?php

use App\Models\Moodboard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('shared-moodboards.show');

render(function (View $view, Moodboard $moodboard) {
    abort_unless($moodboard->is_shared, 404);
});

new class extends Component
{
    public Moodboard $moodboard;

    public function mount(Moodboard $moodboard, string $slug)
    {
        abort_if($moodboard->slug !== $slug, 404);
    }

    public function with()
    {
        $cacheKey = "moodboard:{$this->moodboard->id}:photos";

        $photos = Cache::remember($cacheKey, now()->addHours(1), function () {
            return $this->moodboard->photos()->get()->naturalSortBy('name');
        });

        return [
            'allPhotos' => $photos,
        ];
    }
}; ?>

<x-guest-layout :font="$moodboard->team->brand_font" :color="$moodboard->team->brand_color">
    @volt('pages.shared-moodboards.show')
        <div>
            <div class="relative">
                <a href="{{ route('handle.show', ['handle' => $moodboard->team->handle]) }}">
                    <img
                        src="{{ $moodboard->team->brand_logo_url }}"
                        class="mx-auto max-h-[90px] md:max-h-[160px]"
                    />
                </a>
            </div>

            <div class="mt-4 flex flex-wrap items-end justify-between gap-4 lg:mt-8">
                <div class="max-sm:w-full sm:flex-1">
                    <div class="flex items-center gap-4">
                        <x-heading level="1" size="xl">{{ $moodboard->title }}</x-heading>
                    </div>
                    @if ($moodboard->description)
                        <x-subheading class="mt-2">
                            {{ $moodboard->description }}
                        </x-subheading>
                    @endif
                </div>
            </div>

            @if ($allPhotos->isNotEmpty())
                <div class="mt-8 max-sm:-mx-5">
                    <div class="grid grid-flow-dense grid-cols-3 gap-1 md:grid-cols-4 lg:grid-cols-6">
                        @foreach ($allPhotos as $photo)
                            <livewire:shared-moodboard-photo-item
                                :$photo
                                :key="'photo-'.$photo->id"
                                :html-id="'photo-'.$photo->id"
                            />
                        @endforeach
                    </div>
                </div>
            @else
                <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
                    <flux:icon.photo class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
                    <flux:heading size="lg" level="2">{{ __('No photos') }}</flux:heading>
                    <flux:subheading class="mb-6 max-w-72 text-center">
                        {{ __('We couldn\'t find any photos.') }}
                    </flux:subheading>
                </div>
            @endif
            @unlesssubscribed($moodboard->team)
                <div class="mt-10">
                    @include('partials.powered-by')
                </div>
            @endsubscribed
        </div>
    @endvolt
</x-guest-layout>
