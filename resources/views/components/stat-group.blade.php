@props(['title', 'description' => null, 'icon' => 'chart-bar', 'tone' => 'green'])

@php
    $tones = [
        'green' => [
            'section' => 'bg-brand-sage-soft/60 dark:bg-brand-green/10',
            'badge' => 'bg-brand-green text-brand-cream dark:bg-brand-green-deep',
            'title' => 'text-brand-green-deep dark:text-brand-cream',
        ],
        'coral' => [
            'section' => 'bg-brand-coral/10 dark:bg-brand-coral/10',
            'badge' => 'bg-brand-coral text-brand-cream dark:bg-brand-coral-deep',
            'title' => 'text-brand-coral-deep dark:text-brand-cream',
        ],
        'sage' => [
            'section' => 'bg-brand-sage/25 dark:bg-brand-sage/10',
            'badge' => 'bg-brand-sage text-brand-green-deep dark:text-brand-ink-strong',
            'title' => 'text-brand-green-deep dark:text-brand-cream',
        ],
    ];

    $toneClasses = $tones[$tone] ?? $tones['green'];
@endphp

<section {{ $attributes->class(['p-5 sm:p-6', $toneClasses['section']]) }}>
    <div class="flex items-start gap-3">
        <span class="{{ $toneClasses['badge'] }} flex size-10 shrink-0 items-center justify-center rounded-full shadow-xs">
            <x-ts-icon :icon="$icon" class="size-5" />
        </span>

        <div class="min-w-0">
            <x-ts-heading size="sm" class="{{ $toneClasses['title'] }}">
                {{ $title }}
            </x-ts-heading>

            @if ($description)
                <x-ts-text class="text-brand-ink/60 dark:text-brand-sage/70 mt-0.5 text-xs">
                    {{ $description }}
                </x-ts-text>
            @endif
        </div>
    </div>

    <div class="mt-4 space-y-0.5">
        {{ $slot }}
    </div>
</section>
