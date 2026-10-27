@props([
    'label' => null,
    'description' => null,
    'descriptionTrailing' => null,
    'icon' => null,
    'iconTrailing' => null,
    'kbd' => null,
    'type' => 'text',
    'placeholder' => null,
    'name' => null,
    'as' => null,
    'variant' => null,
    'size' => null,
    'clearable' => null,
    'copyable' => false,
])

@php
    $name ??= $attributes->get('wire:model')
        ?? $attributes->get('wire:model.live')
        ?? $attributes->get('wire:model.defer')
        ?? $attributes->get('wire:model.blur');

    $inputIcon = $iconTrailing ?? $icon;
    $position = $iconTrailing ? 'right' : 'left';
    $hint = $description;
    $filled = $variant === 'filled';
    $sizeClasses = match ($size) {
        'sm' => 'text-sm',
        'xs' => 'text-xs',
        'lg' => 'text-base',
        default => null,
    };
@endphp

@if ($as === 'button')
    <button
        type="button"
        {{ $attributes->class([
            'flex h-11 w-full items-center gap-2 rounded-lg border border-zinc-200 bg-white px-3 text-left text-sm text-zinc-500 shadow-xs transition',
            'dark:border-white/10 dark:bg-white/10 dark:text-zinc-400',
            'hover:bg-brand-sage-soft dark:hover:bg-white/15',
            $sizeClasses,
        ]) }}
    >
        @if ($inputIcon)
            <x-icon :name="$inputIcon" class="size-4 shrink-0" />
        @endif

        <span class="min-w-0 flex-1 truncate">{{ $placeholder ?? $slot }}</span>

        @if ($kbd)
            <x-kbd>{{ $kbd }}</x-kbd>
        @endif
    </button>
@elseif ($type === 'file')
    <label class="block space-y-2">
        @if ($label)
            <span class="block text-sm font-medium text-zinc-800 dark:text-white">{{ $label }}</span>
        @endif

        <input
            type="file"
            name="{{ $name }}"
            {{ $attributes->class([
                'block w-full cursor-pointer rounded-lg border border-zinc-200 bg-white text-sm text-zinc-700 shadow-xs file:mr-3 file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-700 dark:border-white/10 dark:bg-white/10 dark:text-zinc-200 dark:file:bg-white/10 dark:file:text-zinc-200',
                $sizeClasses,
            ]) }}
        />

        @if ($hint)
            <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $hint }}</span>
        @endif
    </label>
@else
    <x-input
        :$label
        :$hint
        :icon="$inputIcon"
        :$position
        :type="$type"
        :placeholder="$placeholder"
        :$name
        :clearable="$clearable"
        @class([
            $filled ? 'border-none bg-white shadow-xs dark:bg-white/10' : null,
            $sizeClasses,
        ])
        {{ $attributes }}
    >
        @if ($kbd || $copyable)
            <x-slot:suffix>
                <span class="flex items-center gap-1 pe-1">
                    @if ($kbd)
                        <x-kbd>{{ $kbd }}</x-kbd>
                    @endif

                    @if ($copyable)
                        <button
                            type="button"
                            x-data="{ copied: false }"
                            x-on:click="
                                const input = $el.closest('.relative')?.querySelector('input');

                                if (! input) {
                                    return;
                                }

                                const done = () => {
                                    copied = true;
                                    setTimeout(() => copied = false, 1500);
                                };

                                if (navigator.clipboard?.writeText) {
                                    navigator.clipboard.writeText(input.value).then(done).catch(() => {
                                        input.select();
                                        document.execCommand('copy');
                                        done();
                                    });
                                } else {
                                    input.select();
                                    document.execCommand('copy');
                                    done();
                                }
                            "
                            class="inline-flex size-7 items-center justify-center rounded-md text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-white/10 dark:hover:text-white"
                            title="{{ __('Copy') }}"
                            aria-label="{{ __('Copy') }}"
                            data-copyable
                        >
                            <x-icon name="clipboard-document" class="size-4" x-show="! copied" />
                            <x-icon
                                name="check"
                                class="size-4 text-brand-green dark:text-brand-sage"
                                x-show="copied"
                                style="display: none"
                            />
                        </button>
                    @endif
                </span>
            </x-slot:suffix>
        @endif
    </x-input>
@endif
