<?php

use App\Models\Gallery;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new
#[Layout('layouts.guest')]
class extends Component
{
    public Team $team;
    public Gallery $gallery;
    public ?Collection $photos;

    public function mount(Gallery $gallery)
    {
        abort_unless($gallery->is_public, 404);

        $this->team = $gallery->team;
        $this->gallery = $gallery;
        $this->photos = $this->gallery->photos()->with('gallery')->get();
    }

    public function rendering(View $view): void
    {
        $view->title($this->gallery->name);
    }
}; ?>

<div class="min-h-screen bg-white dark:bg-zinc-900">
    <div class="h-full">
        @if($photos->isNotEmpty())
            <div class="relative">
                <img src="{{ $gallery->team->brand_logo_url }}" class="mx-auto max-h-[90px] md:max-h-[160px]" />
            </div>

            <div class="relative h-[164px] md:h-[240px] overflow-hidden mt-4 lg:mt-8 max-sm:-mx-6">
                <img src="{{ ($gallery->coverPhoto ?? $photos->first())->url }}" class="w-full h-full object-cover" />
            </div>
        @else
            <div>
                <img src="{{ $gallery->team->brand_logo_url }}" class="mx-auto max-h-[90px] md:max-h-[160px]" />
            </div>
        @endif

        <div class="mt-4 flex flex-wrap items-end justify-between gap-4 lg:mt-8">
            <div class="max-sm:w-full sm:flex-1">
                <div class="flex items-center gap-4">
                    <x-heading level="1" size="xl">{{ $gallery->name }}</x-heading>
                </div>

                @if($gallery->share_description)
                    <x-subheading class="mt-2">
                        {{ $gallery->share_description }}
                    </x-subheading>
                @endif
            </div>
        </div>

        @if ($photos->isNotEmpty())
            <div class="mt-8 max-sm:-mx-5">
                <div class="pt-1">
                    <div
                        class="grid grid-flow-dense grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-1"
                    >
                        @foreach ($photos as $photo)
                            <livewire:shared-photo-item :$photo :key="'photo-'.$photo->id" :html-id="'photo-'.$photo->id" />
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
                <flux:icon.photo class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
                <flux:heading size="lg" level="2">{{ __('No photos') }}</flux:heading>
                <flux:subheading class="mb-6 max-w-72 text-center">
                    {{ __('We couldn’t find any photos.') }}
                </flux:subheading>
            </div>
        @endif

        @unlesssubscribed($gallery->team)
            <div class="mt-10">
                @include('partials.powered-by')
            </div>
        @endsubscribed
    </div>
</div>
