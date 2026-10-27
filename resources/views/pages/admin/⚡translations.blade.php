<?php

use App\Traits\InteractsWithTallStackModals;
use TallStackUi\Traits\Interactions;
use App\Services\TranslationOverrideService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use InteractsWithTallStackModals;
    use Interactions;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'all';

    /**
     * When set, only these keys are shown. The email catalog uses this to
     * deep-link to the editable copy of a single email.
     *
     * @var list<string>
     */
    #[Url]
    public array $keys = [];

    public string $editingKey = '';

    public string $enValue = '';

    public string $esValue = '';

    /** @var list<string> */
    public array $editingPlaceholders = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedKeys(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, array{key: string, en: string, es: ?string, en_preview: string, es_preview: ?string, overridden: bool, missing: bool}>
     */
    #[Computed]
    public function translations(): LengthAwarePaginator
    {
        $service = app(TranslationOverrideService::class);

        $en = $service->effective('en');
        $es = $service->effective('es');
        $enOverrides = $service->overrides('en');
        $esOverrides = $service->overrides('es');

        $keys = array_keys($en + $es);
        sort($keys, SORT_NATURAL | SORT_FLAG_CASE);

        if ($this->keys !== []) {
            $keys = array_values(array_intersect($keys, array_map('strval', $this->keys)));
        }

        $rows = collect($keys)
            ->map(function (string $key) use ($en, $es, $enOverrides, $esOverrides): array {
                $english = $en[$key] ?? $key;
                $spanish = $es[$key] ?? null;

                return [
                    'key' => $key,
                    'en' => $english,
                    'es' => $spanish,
                    'en_preview' => Str::limit($english, 120),
                    'es_preview' => $spanish ? Str::limit($spanish, 120) : null,
                    'overridden' => array_key_exists($key, $enOverrides) || array_key_exists($key, $esOverrides),
                    'missing' => $spanish === null || trim($spanish) === '',
                ];
            })
            ->filter(function (array $row): bool {
                if ($this->search !== '') {
                    $needle = Str::lower($this->search);

                    if (! Str::contains(Str::lower($row['key']), $needle)
                        && ! Str::contains(Str::lower($row['en']), $needle)
                        && ! Str::contains(Str::lower((string) $row['es']), $needle)) {
                        return false;
                    }
                }

                return match ($this->status) {
                    'missing' => $row['missing'],
                    'overridden' => $row['overridden'],
                    default => true,
                };
            })
            ->values();

        $page = Paginator::resolveCurrentPage();
        $perPage = 50;
        $items = $rows->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $rows->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    public function edit(string $key): void
    {
        $service = app(TranslationOverrideService::class);

        $this->editingKey = $key;
        $this->enValue = (string) ($service->value('en', $key) ?? $key);
        $this->esValue = (string) ($service->value('es', $key) ?? '');
        $this->editingPlaceholders = $service->placeholders($key);

        $this->resetValidation();

        $this->openModal('edit-translation');
    }

    public function save(): void
    {
        $this->resetValidation();

        $this->validate([
            'editingKey' => ['required', 'string'],
            'enValue' => ['required', 'string'],
            'esValue' => ['required', 'string'],
        ], [], [
            'enValue' => __('English'),
            'esValue' => __('Spanish'),
        ]);

        if (! $this->placeholdersMatch()) {
            return;
        }

        app(TranslationOverrideService::class)->save([
            'en' => [$this->editingKey => $this->enValue],
            'es' => [$this->editingKey => $this->esValue],
        ]);

        app('translator')->setLoaded([]);
        unset($this->translations);

        $this->closeModal('edit-translation');
        $this->toast()->success(__('Translations saved.'))->send();
    }

    public function resetTranslation(): void
    {
        $service = app(TranslationOverrideService::class);

        $service->reset($this->editingKey);

        $this->enValue = (string) ($service->value('en', $this->editingKey) ?? $this->editingKey);
        $this->esValue = (string) ($service->value('es', $this->editingKey) ?? '');
        $this->editingPlaceholders = $service->placeholders($this->editingKey);

        $this->resetValidation();
        app('translator')->setLoaded([]);
        unset($this->translations);

        $this->toast()->success(__('Translation restored.'))->send();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = 'all';
        $this->keys = [];

        $this->resetPage();
    }

    protected function placeholdersMatch(): bool
    {
        $service = app(TranslationOverrideService::class);

        $allowed = $service->placeholders($this->editingKey);
        $english = $service->placeholders($this->enValue);
        $spanish = $service->placeholders($this->esValue);

        $unknown = array_values(array_diff(array_unique([...$english, ...$spanish]), $allowed));

        if ($unknown !== []) {
            $this->addError('enValue', __('Unknown variables: :variables', [
                'variables' => collect($unknown)->map(fn (string $placeholder): string => ':'.$placeholder)->implode(', '),
            ]));

            return false;
        }

        if (array_diff($english, $spanish) !== [] || array_diff($spanish, $english) !== []) {
            $this->addError('esValue', __('English and Spanish must use the same variables.'));

            return false;
        }

        return true;
    }
}; ?>

<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <x-ts-heading size="xl" class="text-brand-green dark:text-brand-cream font-semibold">
                {{ __('Translations') }}
            </x-ts-heading>
            <x-ts-text class="text-brand-ink/70 dark:text-brand-sage/80 mt-1">
                {{ __('Edit app and email texts in English and Spanish.') }}
            </x-ts-text>
        </div>

        @if ($this->keys !== [])
            <div class="flex items-center gap-2">
                <x-ts-badge color="blue">{{ __('Filtered by email') }}</x-ts-badge>
                <x-ts-button size="sm" variant="subtle" wire:click="clearFilters">
                    {{ __('Show all') }}
                </x-ts-button>
            </div>
        @endif
    </div>

    <x-ts-callout icon="information-circle" variant="secondary" class="mt-6">
        <x-ts-callout-text>
            {{ __('Changes are stored in storage/app/translations and override the language files. Include that folder in backups.') }}
        </x-ts-callout-text>
    </x-ts-callout>

    <div class="mt-6 flex flex-wrap items-end gap-3">
        <x-ts-input
            wire:model.live.debounce.300ms="search"
            :placeholder="__('Search by key or text...')"
            icon="magnifying-glass"
            class="w-full sm:w-80"
            data-test="translation-search"
        />

        <x-ts-select wire:model.live="status" :label="__('Status')" class="w-full sm:w-52">
            <x-ts-select-option value="all">{{ __('All') }}</x-ts-select-option>
            <x-ts-select-option value="missing">{{ __('Missing Spanish') }}</x-ts-select-option>
            <x-ts-select-option value="overridden">{{ __('Overridden') }}</x-ts-select-option>
        </x-ts-select>

        @if ($search !== '' || $status !== 'all' || $keys !== [])
            <x-ts-button variant="subtle" icon="x-mark" wire:click="clearFilters">
                {{ __('Clear filters') }}
            </x-ts-button>
        @endif
    </div>

    <x-data-table class="mt-6">
        <x-data-table.columns>
            <x-data-table.column class="w-[34%]">{{ __('Key') }}</x-data-table.column>
            <x-data-table.column class="w-[22%] max-xl:hidden">{{ __('English') }}</x-data-table.column>
            <x-data-table.column class="w-[22%] max-xl:hidden">{{ __('Spanish') }}</x-data-table.column>
            <x-data-table.column class="w-28">{{ __('Status') }}</x-data-table.column>
            <x-data-table.column class="w-24"></x-data-table.column>
        </x-data-table.columns>

        <x-data-table.rows>
            @forelse ($this->translations as $row)
                <x-data-table.row wire:key="translation-{{ md5($row['key']) }}">
                    <x-data-table.cell class="align-top whitespace-normal">
                        <span class="font-mono text-xs break-all">{{ $row['key'] }}</span>
                    </x-data-table.cell>

                    <x-data-table.cell class="align-top whitespace-normal max-xl:hidden">
                        <span class="text-sm">{{ $row['en_preview'] }}</span>
                    </x-data-table.cell>

                    <x-data-table.cell class="align-top whitespace-normal max-xl:hidden">
                        <span class="text-sm">{{ $row['es_preview'] ?? '—' }}</span>
                    </x-data-table.cell>

                    <x-data-table.cell class="align-top">
                        <div class="flex flex-wrap gap-1">
                            @if ($row['overridden'])
                                <x-ts-badge color="blue" size="sm">{{ __('Overridden') }}</x-ts-badge>
                            @endif

                            @if ($row['missing'])
                                <x-ts-badge color="amber" size="sm">{{ __('Missing Spanish') }}</x-ts-badge>
                            @endif

                            @if (! $row['overridden'] && ! $row['missing'])
                                <x-ts-badge size="sm">{{ __('Default') }}</x-ts-badge>
                            @endif
                        </div>
                    </x-data-table.cell>

                    <x-data-table.cell class="text-end align-top">
                        <x-ts-button
                            size="sm"
                            variant="subtle"
                            icon="pencil-square"
                            wire:click="edit({{ json_encode($row['key'], JSON_HEX_APOS) }})"
                            data-test="edit-{{ md5($row['key']) }}"
                        >
                            {{ __('Edit') }}
                        </x-ts-button>
                    </x-data-table.cell>
                </x-data-table.row>
            @empty
                <x-data-table.row>
                    <x-data-table.cell colspan="5">
                        <x-ts-text class="py-6 text-center">{{ __('No translations found.') }}</x-ts-text>
                    </x-data-table.cell>
                </x-data-table.row>
            @endforelse
        </x-data-table.rows>
    </x-data-table>

    <x-ts-pagination :paginator="$this->translations" />

    <x-ts-modal name="edit-translation" class="w-full max-w-3xl">
        <div class="space-y-6" data-test="translation-editor">
            <div>
                <x-ts-heading size="lg">{{ __('Edit translation') }}</x-ts-heading>
                <x-ts-text class="mt-1 font-mono text-xs break-all">{{ $editingKey }}</x-ts-text>
            </div>

            @if ($editingPlaceholders !== [])
                <div class="flex flex-wrap items-center gap-2">
                    <x-ts-text class="text-sm font-medium">{{ __('Variables:') }}</x-ts-text>

                    @foreach ($editingPlaceholders as $placeholder)
                        <x-ts-badge size="sm">{{ ':'.$placeholder }}</x-ts-badge>
                    @endforeach
                </div>
            @endif

            <x-ts-textarea rows="4" wire:model="enValue" :label="__('English')" />


            <x-ts-textarea rows="4" wire:model="esValue" :label="__('Spanish')" />


            <div class="flex flex-wrap items-center justify-between gap-3">
                <x-ts-button variant="danger" wire:click="resetTranslation" data-test="translation-reset">
                    {{ __('Restore default') }}
                </x-ts-button>

                <div class="flex items-center gap-2">
                    <x-ts-modal-close>
                        <x-ts-button variant="filled">{{ __('Cancel') }}</x-ts-button>
                    </x-ts-modal-close>

                    <x-ts-button variant="primary" wire:click="save" data-test="translation-save">
                        {{ __('Save') }}
                    </x-ts-button>
                </div>
            </div>
        </div>
    </x-ts-modal>
</div>
