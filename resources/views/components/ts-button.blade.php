@props([
    'variant' => null,
    'color' => null,
    'size' => null,
    'icon' => null,
    'iconVariant' => null,
    'iconTrailing' => null,
    'type' => 'button',
    'href' => null,
    'as' => null,
    'loading' => null,
    'tooltip' => null,
    'square' => false,
    'inset' => null,
    'kbd' => null,
])

@php
    $variant ??= 'outline';
    $iconTrailing ??= $attributes->get('icon:trailing') ?? $attributes->get('icon-trailing');

    $isOverlay = $variant === 'overlay';

    $style = match ($variant) {
        'primary', 'danger', 'warning', 'secondary', 'solid' => 'solid',
        'filled' => 'light',
        'subtle', 'ghost' => 'flat',
        default => 'outline',
    };

    $color = $color ?? match ($variant) {
        'primary' => 'primary',
        'danger' => 'red',
        'warning' => 'yellow',
        'secondary', 'solid' => 'zinc',
        default => 'zinc',
    };

    $classes = $attributes->get('class', '');
    $color = str_contains($classes, 'btn-ink') ? 'black' : $color;
    $color = str_contains($classes, 'btn-coral') ? 'secondary' : $color;
    $style = str_contains($classes, 'btn-ink') || str_contains($classes, 'btn-coral') ? 'solid' : $style;

    $sizeClasses = match ($size) {
        'sm' => 'text-sm',
        'xs', 'mini', 'micro' => 'text-xs',
        'lg' => 'text-base',
        default => null,
    };

    $submit = $type === 'submit';
    $buttonIcon = $iconTrailing ?? $icon;
    $position = $iconTrailing ? 'right' : 'left';
    $round = in_array($size, ['xs', 'sm', 'mini', 'micro'], true) ? 'md' : 'lg';

    $padding = match (true) {
        $square => '',
        $iconTrailing && $size === 'sm' => 'ps-3 pe-2',
        $iconTrailing && in_array($size, ['xs', 'mini', 'micro'], true) => 'ps-2 pe-1',
        $iconTrailing => 'ps-4 pe-3',
        $buttonIcon && $size === 'sm' => 'ps-2 pe-3',
        $buttonIcon && in_array($size, ['xs', 'mini', 'micro'], true) => 'ps-1 pe-2',
        $buttonIcon => 'ps-3 pe-4',
        $size === 'sm' => 'px-3',
        in_array($size, ['xs', 'mini', 'micro'], true) => 'px-2',
        default => 'px-4',
    };

    $width = match (true) {
        ! $square => null,
        $size === 'sm' => 'w-8',
        in_array($size, ['xs', 'mini', 'micro'], true) => 'w-6',
        $size === 'lg' => 'w-12',
        default => 'w-10',
    };
@endphp

<x-button
    :$color
    :$href
    :$loading
    :$tooltip
    :icon="$buttonIcon"
    :$position
    :$submit
    :solid="$style === 'solid'"
    :outline="$style === 'outline'"
    :subtle="$style === 'subtle'"
    :flat="$style === 'flat'"
    :xs="$size === 'xs' || $size === 'mini' || $size === 'micro'"
    :sm="$size === 'sm'"
    :lg="$size === 'lg'"
    :round="$round"
    :square="$square"
    @class([
        $classes,
        'font-medium',
        $sizeClasses,
        $padding,
        $width,
        'bg-brand-ink! text-brand-cream! hover:bg-brand-ink-strong! dark:bg-brand-sage! dark:text-brand-ink-strong! dark:hover:bg-brand-sage/90!' => str_contains($classes, 'btn-ink'),
        'bg-brand-coral! text-brand-cream! hover:bg-brand-coral-deep! dark:bg-brand-coral! dark:text-brand-cream! dark:hover:bg-brand-coral-deep!' => str_contains($classes, 'btn-coral'),
        'border-white/70! bg-white/90! text-brand-ink! shadow-md! backdrop-blur-sm hover:bg-white! hover:text-brand-ink! dark:border-white/20! dark:bg-zinc-900/85! dark:text-white! dark:hover:bg-zinc-900! dark:hover:text-white!' => $isOverlay,
    ])
    {{ $attributes->except(['class', 'icon:trailing', 'icon-trailing', 'icon:variant', 'icon-trailing:variant', 'icon:leading']) }}
>
    {{ $slot }}
</x-button>
