@props([
    'label' => null,
    'description' => null,
])

<fieldset {{ $attributes->class(['space-y-2']) }}>
    @if ($label)
        <legend class="text-sm font-medium text-zinc-800 dark:text-white">{{ $label }}</legend>
    @endif

    @if ($description)
        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
    @endif

    <div class="space-y-2">
        {{ $slot }}
    </div>
</fieldset>
