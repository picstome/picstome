@props(['lifetime' => false])

@php
    $benefits = config('plans.benefits', []);
@endphp

<section class="border-brand-green/10 rounded-3xl border bg-white p-5 shadow-xs dark:border-white/10 dark:bg-white/5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-brand-ink dark:text-brand-cream text-base font-semibold">
            {{ __('What changes with Pro') }}
        </h2>

        <x-ts-badge color="green" size="sm" variant="pill">{{ __('All included') }}</x-ts-badge>
    </div>

    <ul class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
        @foreach ($benefits as $benefit)
            @if ($lifetime && $benefit['title'] === 'Galleries that never expire')
                @continue
            @endif

            <li
                class="border-brand-green/10 bg-brand-paper/80 flex flex-col gap-2 rounded-2xl border p-3 dark:border-white/10 dark:bg-white/5"
            >
                <span class="flex items-start gap-2.5">
                    <span
                        class="text-brand-green dark:text-brand-sage bg-brand-sage-soft/70 dark:bg-brand-green/15 flex size-8 shrink-0 items-center justify-center rounded-lg"
                    >
                        <x-icon :name="$benefit['icon']" class="size-4" />
                    </span>

                    <span class="min-w-0">
                        <span class="text-brand-ink dark:text-brand-cream block text-sm leading-tight font-semibold">
                            {{ __($benefit['title']) }}
                        </span>

                        <span class="text-brand-ink/60 dark:text-brand-sage/65 mt-0.5 block text-xs leading-snug">
                            {{ __($benefit['description']) }}
                        </span>
                    </span>
                </span>

                <span
                    class="bg-brand-sage-soft/70 dark:bg-brand-green/15 mt-auto flex flex-wrap items-center gap-x-1.5 gap-y-0.5 rounded-lg px-2 py-1 text-xs font-semibold"
                >
                    <span class="text-brand-ink/40 dark:text-brand-sage/45 font-medium">
                        {{ __($benefit['from']) }}
                    </span>
                    <span aria-hidden="true" class="text-brand-green/40 dark:text-brand-sage/40">&rarr;</span>
                    <span class="text-brand-green dark:text-brand-sage">{{ __($benefit['to']) }}</span>
                </span>
            </li>
        @endforeach
    </ul>
</section>
