<x-guest-layout :font="$team->brand_font" :full-screen="true" :title="$gallery->name">
    <div class="min-h-screen bg-white dark:bg-zinc-900">
        <div class="mx-auto max-w-7xl px-4 py-8">
            <div class="mb-8 text-center">
                <h1 class="text-3xl font-bold text-zinc-900 dark:text-white">{{ $gallery->name }}</h1>
                <p class="mt-2 text-zinc-600 dark:text-zinc-400">{{ $team->name }}</p>
                @if ($gallery->share_description)
                    <p class="mt-4 text-zinc-700 dark:text-zinc-300">{{ $gallery->share_description }}</p>
                @endif
            </div>

            @if ($allPhotos->isNotEmpty())
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                    @foreach ($allPhotos as $photo)
                        <div class="aspect-square overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
                            <img
                                src="{{ $photo->url }}"
                                alt="{{ $photo->name }}"
                                class="h-full w-full object-cover"
                            />
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <p class="text-zinc-500 dark:text-zinc-400">No photos in this gallery yet.</p>
                </div>
            @endif
        </div>
    </div>
</x-guest-layout>