<?php

use App\Livewire\Forms\ProductForm;
use App\Models\BookingSetting;
use App\Models\Team;
use App\Services\Booking\LocationSearch;
use App\Traits\InteractsWithTallStackModals;
use Illuminate\Support\Facades\Auth;
use Laravel\Cashier\Cashier;
use Livewire\Attributes\Computed;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * Product list and editor, shared by the booking settings page and the
 * Products tab of the agenda. Keeping the CRUD in one component avoids two
 * copies of the same markup drifting apart.
 */
new class extends Component
{
    use Interactions;
    use InteractsWithTallStackModals;

    public ProductForm $productForm;

    public ?int $pendingDeleteId = null;

    /**
     * @var array<int, array{label: string, latitude: float, longitude: float}>
     */
    public array $locationResults = [];

    public ?string $locationMessage = null;

    public bool $openProductOnLoad = false;

    public function mount(): void
    {
        if (request()->filled('product')) {
            $product = $this->team->products()->findOrFail((int) request('product'));

            $this->productForm->setProduct($product);
            $this->openProductOnLoad = true;
        }
    }

    #[Computed]
    public function team(): Team
    {
        return Auth::user()->currentTeam;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product>
     */
    #[Computed]
    public function products()
    {
        return $this->team->products()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function settings(): BookingSetting
    {
        return BookingSetting::forTeam($this->team);
    }

    public function openProductModal(?int $productId = null): void
    {
        $this->productForm->resetForm();
        $this->locationResults = [];
        $this->locationMessage = null;

        if ($productId !== null) {
            $this->productForm->setProduct($this->team->products()->findOrFail($productId));
        }

        $this->openModal('product-form');
    }

    public function saveProduct(): void
    {
        $this->productForm->save($this->team);

        unset($this->products);

        $this->closeModal('product-form');

        $this->productForm->resetForm();

        $this->toast()->success(__('Product saved.'))->send();
    }

    public function requestDeleteProduct(int $productId): void
    {
        $this->pendingDeleteId = $productId;

        $this->openModal('delete-product');
    }

    public function deleteProduct(): void
    {
        if ($this->pendingDeleteId === null) {
            return;
        }

        $this->team->products()->findOrFail($this->pendingDeleteId)->delete();

        $this->pendingDeleteId = null;

        unset($this->products);

        $this->closeModal('delete-product');

        $this->toast()->success(__('Product deleted.'))->send();
    }

    public function toggleProduct(int $productId): void
    {
        $product = $this->team->products()->findOrFail($productId);

        $product->update(['is_active' => ! $product->is_active]);

        unset($this->products);

        $this->toast()->success(
            $product->is_active ? __('Product activated.') : __('Product paused.')
        )->send();
    }

    /**
     * Move a product to the position of another one, respecting the drag
     * direction: dragging down drops after the target, dragging up drops
     * before it.
     */
    public function reorderProducts(int $draggedId, int $targetId): void
    {
        $ids = $this->products->pluck('id')->all();

        $draggedIndex = array_search($draggedId, $ids, true);
        $targetIndex = array_search($targetId, $ids, true);

        if ($draggedIndex === false || $targetIndex === false || $draggedIndex === $targetIndex) {
            return;
        }

        $movingDown = $draggedIndex < $targetIndex;

        $ids = array_values(array_diff($ids, [$draggedId]));
        $position = array_search($targetId, $ids, true);

        if ($position === false) {
            return;
        }

        if ($movingDown) {
            $position++;
        }

        array_splice($ids, $position, 0, [$draggedId]);

        foreach ($ids as $index => $id) {
            $this->team->products()->whereKey($id)->update(['sort_order' => $index]);
        }

        unset($this->products);
    }

    public function searchLocation(): void
    {
        $this->locationResults = app(LocationSearch::class)
            ->search((string) $this->productForm->location_name);

        $this->locationMessage = $this->locationResults === []
            ? __('No location found. Try a more specific name.')
            : null;
    }

    public function selectLocation(int $index): void
    {
        $result = $this->locationResults[$index] ?? null;

        if ($result === null) {
            return;
        }

        $this->productForm->location_name = $result['label'];
        $this->productForm->latitude = (string) $result['latitude'];
        $this->productForm->longitude = (string) $result['longitude'];
        $this->locationResults = [];
        $this->locationMessage = __('Coordinates saved. The sunset time can be calculated now.');
    }

    public function useCurrentLocation(float $latitude, float $longitude): void
    {
        $result = app(LocationSearch::class)->reverse($latitude, $longitude);

        $this->productForm->latitude = (string) round($latitude, 7);
        $this->productForm->longitude = (string) round($longitude, 7);

        if ($result !== null) {
            $this->productForm->location_name = $result['label'];
        }

        $this->locationResults = [];
        $this->locationMessage = __('Location detected.');
    }

    public function locationFailed(): void
    {
        $this->locationMessage = __('We could not get your location. Type the place and search instead.');
    }

    public function clearLocation(): void
    {
        $this->productForm->latitude = '';
        $this->productForm->longitude = '';
        $this->locationResults = [];
        $this->locationMessage = null;
    }

    public function formatAmount(int $minorUnits, string $currency): string
    {
        return Cashier::formatAmount($minorUnits, $currency);
    }
}; ?>

<div class="space-y-6" x-data x-init="if ({{ $openProductOnLoad ? 'true' : 'false' }}) { $nextTick(() => $tsui.open.modal('product-form')) }">
    <x-ts-card :heading="__('Products')">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-ts-text variant="subtle" size="sm">
                {{ __('Each product has its own duration and price, and can define its own deposit.') }}
                · {{ __('Drag to reorder') }}
            </x-ts-text>
            <x-ts-button type="button" variant="primary" icon="plus" wire:click="openProductModal">
                {{ __('New product') }}
            </x-ts-button>
        </div>

        @if ($this->products->isEmpty())
            <div class="mt-10 flex flex-col items-center justify-center pb-10 text-center">
                <x-icon name="briefcase" class="mb-4 size-10 text-zinc-400" />
                <x-ts-heading size="lg">{{ __('No products yet') }}</x-ts-heading>
                <x-ts-subheading class="mt-2 max-w-sm">
                    {{ __('Create a product for each type of session you offer.') }}
                </x-ts-subheading>
            </div>
        @else
            <div
                class="mt-5 overflow-hidden rounded-xl border border-zinc-200 dark:border-white/10"
                x-data="{ draggedId: null }"
            >
                @foreach ($this->products as $product)
                    <div
                        class="flex flex-wrap items-center gap-4 border-b border-zinc-100 px-4 py-3 last:border-b-0 dark:border-white/10"
                        wire:key="product-{{ $product->id }}"
                        draggable="true"
                        x-on:dragstart="draggedId = {{ $product->id }}"
                        x-on:dragover.prevent
                        x-on:drop.prevent="if (draggedId !== null) { $wire.reorderProducts(draggedId, {{ $product->id }}); draggedId = null }"
                        x-on:dragend="draggedId = null"
                    >
                        <x-icon name="bars-3" class="size-4 shrink-0 cursor-grab text-zinc-400" />

                        <div
                            class="min-w-0 flex-1 cursor-pointer"
                            wire:click="openProductModal({{ $product->id }})"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($product->color)
                                    <span @class([
                                        'size-2.5 shrink-0 rounded-full',
                                        'bg-brand-green' => $product->color === 'green',
                                        'bg-brand-coral' => $product->color === 'coral',
                                        'bg-brand-sage' => $product->color === 'sage',
                                        'bg-amber-400' => $product->color === 'amber',
                                        'bg-blue-500' => $product->color === 'blue',
                                        'bg-violet-500' => $product->color === 'violet',
                                        'bg-zinc-400' => $product->color === 'zinc',
                                    ])></span>
                                @endif

                                <p class="text-brand-ink truncate font-medium dark:text-white">{{ $product->name }}</p>
                                @if (! $product->is_active)
                                    <x-ts-badge color="zinc" size="sm">{{ __('Inactive') }}</x-ts-badge>
                                @endif
                                @if ($product->light_mode->value !== 'none')
                                    <x-ts-badge color="amber" size="sm" icon="sun">
                                        {{ $product->light_mode === \App\Enums\LightMode::Sunset ? __('Sunset') : __('Sunrise') }}
                                    </x-ts-badge>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $product->duration_minutes }} {{ __('min') }} ·
                                {{ $this->formatAmount($product->price, $product->currency) }}
                                @if ($product->depositAmount($this->settings) > 0)
                                    · {{ __('Deposit') }} {{ $this->formatAmount($product->depositAmount($this->settings), $product->currency) }}
                                @endif
                                @if ($product->location_name)
                                    · {{ $product->location_name }}
                                @endif
                            </p>
                        </div>

                        @if ($product->is_active)
                            <x-ts-button
                                :href="route('handle.book', ['handle' => $this->team->handle, 'productUlid' => $product->ulid])"
                                target="_blank"
                                variant="ghost"
                                icon="eye"
                                :aria-label="__('Preview')"
                            />

                            <button
                                type="button"
                                x-data="{ copied: false }"
                                x-on:click="navigator.clipboard.writeText(@js(route('handle.book', ['handle' => $this->team->handle, 'productUlid' => $product->ulid]))); copied = true; setTimeout(() => copied = false, 1500)"
                                class="rounded-md p-1.5 text-zinc-500 transition hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-white/10"
                                title="{{ __('Copy booking link') }}"
                                aria-label="{{ __('Copy booking link') }}"
                            >
                                <x-icon name="link" class="size-4" x-show="!copied" />
                                <x-icon name="check" class="size-4 text-emerald-500" x-show="copied" x-cloak />
                            </button>
                        @endif

                        <button
                            type="button"
                            role="switch"
                            aria-checked="{{ $product->is_active ? 'true' : 'false' }}"
                            wire:click="toggleProduct({{ $product->id }})"
                            @class([
                                'relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition',
                                'bg-brand-green' => $product->is_active,
                                'bg-zinc-300 dark:bg-white/20' => ! $product->is_active,
                            ])
                        >
                            <span
                                @class([
                                    'inline-block size-4 rounded-full bg-white shadow transition',
                                    'translate-x-4' => $product->is_active,
                                    'translate-x-0.5' => ! $product->is_active,
                                ])
                            ></span>
                        </button>

                        <div class="flex items-center gap-1">
                            <x-ts-button
                                type="button"
                                variant="ghost"
                                icon="pencil-square"
                                wire:click="openProductModal({{ $product->id }})"
                            />
                            <x-ts-button
                                type="button"
                                variant="ghost"
                                icon="trash"
                                wire:click="requestDeleteProduct({{ $product->id }})"
                            />
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ts-card>

    <x-ts-modal name="product-form" class="w-full sm:max-w-2xl">
        <form wire:submit="saveProduct" class="space-y-5">
            <div>
                <x-ts-heading size="lg">
                    {{ $productForm->product_id ? __('Edit product') : __('New product') }}
                </x-ts-heading>
                <x-ts-subheading>
                    {{ __('Duration, price and the light you need for this type of session.') }}
                </x-ts-subheading>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-ts-input wire:model="productForm.name" :label="__('Name')" required />
                </div>

                <div class="sm:col-span-2">
                    <x-ts-textarea wire:model="productForm.description" :label="__('Description')" rows="2" />
                </div>

                <x-ts-input wire:model="productForm.duration_minutes" type="number" min="5" :label="__('Duration in minutes')" required />

                <x-ts-input wire:model="productForm.price" type="number" step="0.01" min="0" :label="__('Price')" required />

                <x-ts-select wire:model.live="productForm.deposit_type" :label="__('Deposit')">
                    <x-ts-select-option value="">{{ __('Use the team default') }}</x-ts-select-option>
                    <x-ts-select-option value="none">{{ __('No deposit') }}</x-ts-select-option>
                    <x-ts-select-option value="fixed">{{ __('Fixed amount') }}</x-ts-select-option>
                    <x-ts-select-option value="percent">{{ __('Percentage') }}</x-ts-select-option>
                </x-ts-select>

                @if (in_array($productForm->deposit_type, ['fixed', 'percent'], true))
                    <x-ts-input
                        wire:model="productForm.deposit_value"
                        type="number"
                        min="0"
                        :label="$productForm->deposit_type === 'percent' ? __('Deposit percentage') : __('Deposit amount')"
                    />
                @endif

                <x-ts-field>
                    <x-ts-label>{{ __('Buffer after the session') }}</x-ts-label>
                    <x-ts-input-group>
                        <x-ts-input wire:model="productForm.buffer_minutes" type="number" min="0" />
                        <x-ts-input-group-suffix>{{ __('min') }}</x-ts-input-group-suffix>
                    </x-ts-input-group>
                    <x-ts-description>{{ __('Leave empty to use the team default.') }}</x-ts-description>
                </x-ts-field>

                <div class="sm:col-span-2">
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="min-w-0 flex-1">
                            <x-ts-input
                                wire:model="productForm.location_name"
                                :label="__('Location')"
                                :description="__('Town, beach or venue. Search it and the coordinates are filled automatically.')"
                            />
                        </div>
                        <x-ts-button type="button" variant="secondary" icon="magnifying-glass" wire:click="searchLocation">
                            {{ __('Search') }}
                        </x-ts-button>
                        <x-ts-button
                            type="button"
                            variant="ghost"
                            icon="map-pin"
                            x-on:click="navigator.geolocation?.getCurrentPosition(
                                (position) => $wire.useCurrentLocation(position.coords.latitude, position.coords.longitude),
                                () => $wire.locationFailed()
                            )"
                        >
                            {{ __('My location') }}
                        </x-ts-button>
                    </div>

                    @if (count($locationResults))
                        <div class="mt-2 divide-y divide-zinc-100 overflow-hidden rounded-lg border border-zinc-200 dark:divide-white/10 dark:border-white/10">
                            @foreach ($locationResults as $index => $result)
                                <button
                                    type="button"
                                    wire:key="location-result-{{ $index }}"
                                    wire:click="selectLocation({{ $index }})"
                                    class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left hover:bg-zinc-50 dark:hover:bg-white/5"
                                >
                                    <span class="min-w-0 truncate text-sm">{{ $result['label'] }}</span>
                                    <span class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ round($result['latitude'], 5) }}, {{ round($result['longitude'], 5) }}
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    @if ($productForm->latitude !== '' && $productForm->longitude !== '')
                        <div class="mt-2 flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                            <x-icon name="check-circle" class="size-4 text-emerald-500" />
                            <span>{{ $productForm->latitude }}, {{ $productForm->longitude }}</span>
                            <button type="button" wire:click="clearLocation" class="underline">{{ __('Remove') }}</button>
                        </div>
                    @endif

                    @if ($locationMessage)
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $locationMessage }}</p>
                    @endif
                </div>

                <x-ts-select wire:model="productForm.light_mode" :label="__('Natural light')">
                    <x-ts-select-option value="none">{{ __('Not relevant') }}</x-ts-select-option>
                    <x-ts-select-option value="sunset">{{ __('Sunset session') }}</x-ts-select-option>
                    <x-ts-select-option value="sunrise">{{ __('Sunrise session') }}</x-ts-select-option>
                </x-ts-select>

                <x-ts-select wire:model="productForm.color" :label="__('Colour')">
                    <x-ts-select-option value="">{{ __('No colour') }}</x-ts-select-option>
                    <x-ts-select-option value="green">{{ __('Green') }}</x-ts-select-option>
                    <x-ts-select-option value="coral">{{ __('Coral') }}</x-ts-select-option>
                    <x-ts-select-option value="sage">{{ __('Sage') }}</x-ts-select-option>
                    <x-ts-select-option value="amber">{{ __('Amber') }}</x-ts-select-option>
                    <x-ts-select-option value="blue">{{ __('Blue') }}</x-ts-select-option>
                    <x-ts-select-option value="violet">{{ __('Violet') }}</x-ts-select-option>
                    <x-ts-select-option value="zinc">{{ __('Grey') }}</x-ts-select-option>
                </x-ts-select>

                @if ($productForm->light_mode !== 'none')
                    <p class="self-end text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('The recommended time is calculated from the coordinates of the location.') }}
                    </p>
                @endif
            </div>

            <x-ts-switch wire:model="productForm.is_active" :label="__('Product active')" />

            <div class="flex items-center gap-3">
                <x-ts-spacer />
                <x-ts-button type="button" variant="secondary" wire:click="closeModal('product-form')">
                    {{ __('Cancel') }}
                </x-ts-button>
                <x-ts-button type="submit" variant="primary">{{ __('Save product') }}</x-ts-button>
            </div>
        </form>
    </x-ts-modal>

    <x-ts-modal name="delete-product" class="w-full sm:max-w-md">
        <div class="space-y-4">
            <x-ts-heading size="lg">{{ __('Delete product') }}</x-ts-heading>
            <x-ts-text>
                {{ __('Bookings already created keep their own data. This only stops the product from being offered.') }}
            </x-ts-text>
            <div class="flex items-center gap-3">
                <x-ts-spacer />
                <x-ts-button type="button" variant="secondary" wire:click="closeModal('delete-product')">
                    {{ __('Cancel') }}
                </x-ts-button>
                <x-ts-button type="button" variant="danger" wire:click="deleteProduct">
                    {{ __('Delete') }}
                </x-ts-button>
            </div>
        </div>
    </x-ts-modal>
</div>
