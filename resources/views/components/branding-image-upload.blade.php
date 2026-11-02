@props([
    'property',
    'label',
    'description' => null,
    'maxWidth' => null,
    'maxHeight' => null,
    'square' => false,
])

<div
    x-data="brandingImageUpload({
        property: '{{ $property }}',
        maxWidth: {{ $maxWidth ?? 'null' }},
        maxHeight: {{ $maxHeight ?? 'null' }},
        square: {{ $square ? 'true' : 'false' }},
    })"
>
    <x-ts-input
        :$label
        :$description
        type="file"
        accept="image/png,image/webp,image/jpeg"
        x-on:change="select($event)"
    />

    <div class="mt-1 space-y-1">
        <p x-show="uploading" x-cloak class="text-xs text-zinc-500 dark:text-zinc-400">
            {{ __('Uploading...') }} <span x-text="`${progress}%`"></span>
        </p>

        <p x-show="error" x-cloak x-text="error" class="text-xs text-red-600 dark:text-red-400"></p>

        <x-ts-error :name="$property" />
    </div>
</div>
