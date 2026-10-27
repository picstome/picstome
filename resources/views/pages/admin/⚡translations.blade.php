<?php

use App\Services\TranslationOverrideService;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
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

        Flux::modal('edit-translation')->show();
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

        Flux::modal('edit-translation')->close();
        Flux::toast(__('Translations saved.'));
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

        Flux::toast(__('Translation restored.'));
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
            <flux:heading size="xl" class="text-brand-green dark:text-brand-cream font-semibold">
                {{ __('Translations') }}
            </flux:heading>
            <flux:text class="text-brand-ink/70 dark:text-brand-sage/80 mt-1">
                {{ __('Edit app and email texts in English and Spanish.') }}
            </flux:text>
        </div>

        @if ($this->keys !== [])
            <div class="flex items-center gap-2">
                <flux:badge color="blue">{{ __('Filtered by email') }}</flux:badge>
                <flux:button size="sm" variant="subtle" wire:click="clearFilters">
                    {{ __('Show all') }}
                </flux:button>
            </div>
        @endif
    </div>

    <flux:callout icon="information-circle" variant="secondary" class="mt-6">
        <flux:callout.text>
            {{ __('Changes are stored in storage/app/translations and override the language files. Include that folder in backups.') }}
        </flux:callout.text>
    </flux:callout>

    <div class="mt-6 flex flex-wrap items-end gap-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            :placeholder="__('Search by key or text...')"
            icon="magnifying-glass"
            class="w-full sm:w-80"
            data-test="translation-search"
        />

        <flux:select wire:model.live="status" :label="__('Status')" class="w-full sm:w-52">
            <flux:select.option value="all">{{ __('All') }}</flux:select.option>
            <flux:select.option value="missing">{{ __('Missing Spanish') }}</flux:select.option>
            <flux:select.option value="overridden">{{ __('Overridden') }}</flux:select.option>
        </flux:select>

        @if ($search !== '' || $status !== 'all' || $keys !== [])
            <flux:button variant="subtle" icon="x-mark" wire:click="clearFilters">
                {{ __('Clear filters') }}
            </flux:button>
        @endif
    </div>

    <flux:table :paginate="$this->translations" class="mt-6">
        <flux:table.columns>
            <flux:table.column class="w-[34%]">{{ __('Key') }}</flux:table.column>
            <flux:table.column class="w-[22%] max-xl:hidden">{{ __('English') }}</flux:table.column>
            <flux:table.column class="w-[22%] max-xl:hidden">{{ __('Spanish') }}</flux:table.column>
            <flux:table.column class="w-28">{{ __('Status') }}</flux:table.column>
            <flux:table.column class="w-24"></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->translations as $row)
                <flux:table.row wire:key="translation-{{ md5($row['key']) }}">
                    <flux:table.cell class="align-top whitespace-normal">
                        <span class="font-mono text-xs break-all">{{ $row['key'] }}</span>
                    </flux:table.cell>

                    <flux:table.cell class="align-top whitespace-normal max-xl:hidden">
                        <span class="text-sm">{{ $row['en_preview'] }}</span>
                    </flux:table.cell>

                    <flux:table.cell class="align-top whitespace-normal max-xl:hidden">
                        <span class="text-sm">{{ $row['es_preview'] ?? '—' }}</span>
                    </flux:table.cell>

                    <flux:table.cell class="align-top">
                        <div class="flex flex-wrap gap-1">
                            @if ($row['overridden'])
                                <flux:badge color="blue" size="sm">{{ __('Overridden') }}</flux:badge>
                            @endif

                            @if ($row['missing'])
                                <flux:badge color="amber" size="sm">{{ __('Missing Spanish') }}</flux:badge>
                            @endif

                            @if (! $row['overridden'] && ! $row['missing'])
                                <flux:badge size="sm">{{ __('Default') }}</flux:badge>
                            @endif
                        </div>
                    </flux:table.cell>

                    <flux:table.cell class="text-end align-top">
                        <flux:button
                            size="sm"
                            variant="subtle"
                            icon="pencil-square"
                            wire:click="edit({{ json_encode($row['key'], JSON_HEX_APOS) }})"
                            data-test="edit-{{ md5($row['key']) }}"
                        >
                            {{ __('Edit') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <flux:text class="py-6 text-center">{{ __('No translations found.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="edit-translation" class="w-full max-w-3xl">
        <div class="space-y-6" data-test="translation-editor">
            <div>
                <flux:heading size="lg">{{ __('Edit translation') }}</flux:heading>
                <flux:text class="mt-1 font-mono text-xs break-all">{{ $editingKey }}</flux:text>
            </div>

            @if ($editingPlaceholders !== [])
                <div class="flex flex-wrap items-center gap-2">
                    <flux:text class="text-sm font-medium">{{ __('Variables:') }}</flux:text>

                    @foreach ($editingPlaceholders as $placeholder)
                        <flux:badge size="sm">{{ ':'.$placeholder }}</flux:badge>
                    @endforeach
                </div>
            @endif

            <flux:textarea rows="4" wire:model="enValue" :label="__('English')" />
            <flux:error name="enValue" />

            <flux:textarea rows="4" wire:model="esValue" :label="__('Spanish')" />
            <flux:error name="esValue" />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:button variant="danger" wire:click="resetTranslation" data-test="translation-reset">
                    {{ __('Restore default') }}
                </flux:button>

                <div class="flex items-center gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="primary" wire:click="save" data-test="translation-save">
                        {{ __('Save') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>
</div>
