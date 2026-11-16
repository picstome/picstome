@php
    $helpUrl = app(\App\Support\Help\HelpLink::class)->url();
@endphp

<a
    href="{{ $helpUrl }}"
    target="_blank"
    rel="noopener noreferrer"
    {{ $attributes->class([
        'text-zinc-500 hover:bg-brand-sage-soft hover:text-brand-green dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white inline-flex size-8 shrink-0 items-center justify-center rounded-lg transition',
    ]) }}
    x-tooltip="{{ __('Get help') }}"
    aria-label="{{ __('Get help') }}"
    data-help-link
>
    <x-icon name="question-mark-circle" class="size-5" />
</a>
