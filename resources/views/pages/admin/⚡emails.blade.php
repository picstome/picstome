<?php

use App\Services\TranslationOverrideService;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     trigger: string,
     *     when: string,
     *     recipients: string,
     *     preview_fields: list<array{key: string, label: string, type: string, preview: string}>,
     *     remaining_fields: int,
     *     banner_fields: list<array{key: string, label: string, type: string, value: string}>,
     *     translation_keys: list<string>
     * }>
     */
    #[Computed]
    public function emails(): array
    {
        $locale = app()->getLocale();
        $translations = app(TranslationOverrideService::class)->effective($locale);

        return collect(config('emails'))
            ->map(function (array $email, string $key) use ($locale, $translations): array {
                $fields = collect($email['fields'])
                    ->map(fn (array $field): array => [
                        ...$field,
                        'value' => $translations[$field['key']] ?? $field['key'],
                    ]);

                $bannerFields = collect($email['banner_fields'] ?? [])
                    ->map(fn (array $field): array => [
                        ...$field,
                        'value' => $translations[$field['key']] ?? $field['key'],
                    ])
                    ->values()
                    ->all();

                $translationKeys = collect($email['fields'])
                    ->merge($email['banner_fields'] ?? [])
                    ->pluck('key')
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'key' => $key,
                    'label' => $email['label'][$locale] ?? $email['label']['en'],
                    'description' => $email['description'][$locale] ?? $email['description']['en'],
                    'trigger' => $email['trigger'][$locale] ?? $email['trigger']['en'],
                    'when' => $email['when'][$locale] ?? $email['when']['en'],
                    'recipients' => $email['recipients'][$locale] ?? $email['recipients']['en'],
                    'preview_fields' => $fields
                        ->take(3)
                        ->map(fn (array $field): array => [
                            ...$field,
                            'preview' => Str::limit($field['value'], 140),
                        ])
                        ->values()
                        ->all(),
                    'remaining_fields' => max(0, $fields->count() - 3),
                    'banner_fields' => $bannerFields,
                    'translation_keys' => $translationKeys,
                ];
            })
            ->values()
            ->all();
    }
}; ?>

<div>
    <div>
        <flux:heading size="xl" class="text-brand-green dark:text-brand-cream font-semibold">
            {{ __('Emails') }}
        </flux:heading>
        <flux:text class="text-brand-ink/70 dark:text-brand-sage/80 mt-1">
            {{ __('Transactional emails') }}
        </flux:text>
    </div>

    <flux:callout icon="information-circle" variant="secondary" class="mt-6">
        <flux:callout.text>
            {{ __('This catalog shows the email templates and their triggers. It is not a delivery log; Acumbamail keeps the delivery history.') }}
        </flux:callout.text>
    </flux:callout>

    <div class="mt-6 space-y-4">
        @foreach ($this->emails as $email)
            <flux:card class="p-0!" wire:key="email-{{ $email['key'] }}" data-test="email-card-{{ $email['key'] }}">
                <div class="border-brand-green/10 border-b px-5 py-4 dark:border-white/10">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <flux:heading size="lg" class="text-brand-green dark:text-brand-cream font-semibold">
                                {{ $email['label'] }}
                            </flux:heading>
                            <flux:text class="text-brand-ink/70 dark:text-brand-sage/80 mt-1">
                                {{ $email['description'] }}
                            </flux:text>
                        </div>

                        <flux:button
                            :href="route('admin.translations', ['keys' => $email['translation_keys']])"
                            variant="primary"
                            icon="pencil-square"
                            size="sm"
                            data-test="edit-email-{{ $email['key'] }}"
                        >
                            {{ __('Edit texts') }}
                        </flux:button>
                    </div>
                </div>

                <div class="grid gap-5 px-5 py-5 lg:grid-cols-[1.1fr_1fr]">
                    <div class="space-y-3">
                        <div>
                            <flux:text
                                class="text-brand-ink/55 dark:text-brand-sage/70 text-xs font-semibold tracking-wide uppercase"
                            >
                                {{ __('Trigger') }}
                            </flux:text>
                            <flux:text class="mt-1 text-sm">{{ $email['trigger'] }}</flux:text>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <flux:text
                                    class="text-brand-ink/55 dark:text-brand-sage/70 text-xs font-semibold tracking-wide uppercase"
                                >
                                    {{ __('When') }}
                                </flux:text>
                                <flux:text class="mt-1 text-sm">{{ $email['when'] }}</flux:text>
                            </div>

                            <div>
                                <flux:text
                                    class="text-brand-ink/55 dark:text-brand-sage/70 text-xs font-semibold tracking-wide uppercase"
                                >
                                    {{ __('Recipients') }}
                                </flux:text>
                                <flux:text class="mt-1 text-sm">{{ $email['recipients'] }}</flux:text>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <flux:text
                            class="text-brand-ink/55 dark:text-brand-sage/70 text-xs font-semibold tracking-wide uppercase"
                        >
                            {{ __('Content') }}
                        </flux:text>

                        @foreach ($email['preview_fields'] as $field)
                            <div class="border-brand-green/10 rounded-xl border p-3 dark:border-white/10">
                                <flux:text class="text-brand-ink/55 dark:text-brand-sage/70 text-xs font-semibold">
                                    {{ __($field['label']) }}
                                </flux:text>
                                <flux:text class="mt-1 text-sm break-words whitespace-normal">
                                    {{ $field['preview'] }}
                                </flux:text>
                            </div>
                        @endforeach

                        @if ($email['remaining_fields'] > 0)
                            <flux:text class="text-brand-ink/55 dark:text-brand-sage/70 text-xs">
                                {{ __('+ :count more texts', ['count' => $email['remaining_fields']]) }}
                            </flux:text>
                        @endif

                        @if ($email['banner_fields'] !== [])
                            <div
                                class="rounded-xl border border-amber-300/60 bg-amber-50/60 p-3 dark:border-amber-400/30 dark:bg-amber-400/10"
                            >
                                <flux:badge color="amber" size="sm">{{ __('In-app banner') }}</flux:badge>

                                @foreach ($email['banner_fields'] as $field)
                                    <div class="mt-2">
                                        <flux:text
                                            class="text-brand-ink/55 dark:text-brand-sage/70 text-xs font-semibold"
                                        >
                                            {{ __($field['label']) }}
                                        </flux:text>
                                        <flux:text class="mt-1 text-sm break-words whitespace-normal">
                                            {{ Str::limit($field['value'], 140) }}
                                        </flux:text>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </flux:card>
        @endforeach
    </div>
</div>
