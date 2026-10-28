<?php

use App\Traits\InteractsWithTallStackModals;
use App\Livewire\Forms\CustomerForm;
use App\Models\Customer;
use App\Traits\FormatsMoney;
use Illuminate\Support\Facades\Auth;
use Laravel\Cashier\Cashier;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use InteractsWithTallStackModals;
    use FormatsMoney;

    public Customer $customer;

    public CustomerForm $form;

    public bool $editingNotes = false;

    public ?string $editedNotes = null;

    public function mount()
    {
        $this->authorize('view', $this->customer);

        $this->form->setCustomer($this->customer);
    }

    #[Computed]
    public function team()
    {
        return Auth::user()->currentTeam;
    }

    #[Computed]
    public function invoices()
    {
        return $this->customer->invoices()->latest('id')->get();
    }

    public function update()
    {
        $this->form->update();

        $this->customer = $this->customer->fresh();

        $this->closeModal('edit');
    }

    public function startEditingNotes()
    {
        $this->editedNotes = $this->customer->notes;
        $this->editingNotes = true;
    }

    public function saveNotes()
    {
        $this->customer->notes = $this->editedNotes;
        $this->customer->save();
        $this->editingNotes = false;
        $this->customer = $this->customer->fresh();
    }

    public function cancelEditingNotes()
    {
        $this->editingNotes = false;
    }

    public function delete()
    {
        $this->customer->delete();
        $this->redirect(route('customers'));
    }

    #[Computed]
    public function galleries()
    {
        return $this->customer->photoshoots->flatMap->galleries;
    }

    #[Computed]
    public function sharedGalleriesCount(): int
    {
        return $this->customer->galleries()
            ->where('is_shared', true)
            ->whereNull('share_password')
            ->count();
    }

    #[Computed]
    public function contracts()
    {
        return $this->customer->photoshoots->flatMap->contracts;
    }

    #[Computed]
    public function payments()
    {
        return $this->customer->photoshoots->flatMap->payments->sortByDesc('completed_at');
    }

    #[Computed]
    public function stats()
    {
        $photoshoots = $this->customer->photoshoots;
        $galleries = $photoshoots->flatMap->galleries;
        $photos = $galleries->flatMap->photos;
        $payments = $photoshoots->flatMap->payments->whereNotNull('completed_at');
        $totalPayments = $payments->sum('amount');

        return [
            [
                'title' => __('Photoshoots'),
                'value' => $photoshoots->count(),
            ],
            [
                'title' => __('Galleries'),
                'value' => $galleries->count(),
            ],
            [
                'title' => __('Photos'),
                'value' => $photos->count(),
            ],
            [
                'title' => __('Payments'),
                'value' => Cashier::formatAmount(
                    $totalPayments,
                    $this->team->stripe_currency
                ),
            ],
        ];
    }
}; ?>

<div>
    <div class="max-lg:hidden">
        <x-ts-button :href="route('customers')" icon="chevron-left" variant="subtle" inset wire:navigate>
            {{ __('Customers') }}
        </x-ts-button>
    </div>

    <div class="mt-4 flex flex-wrap items-end justify-between gap-4 lg:mt-8">
        <div class="max-sm:w-full sm:flex-1">
            <x-ts-heading level="1" size="xl">{{ $customer->name }}</x-ts-heading>
            @if ($this->sharedGalleriesCount === 0)
                <x-ts-text class="mt-1">{{ __('This client has no shared galleries.') }}</x-ts-text>
            @endif
        </div>
        <div class="flex gap-4">
            @if ($this->sharedGalleriesCount > 0)
                <x-ts-modal-trigger name="client-link">
                    <x-ts-button icon="link" variant="primary">{{ __('Client link') }}</x-ts-button>
                </x-ts-modal-trigger>
            @endif
            <x-ts-modal-trigger name="delete-customer">
                <x-ts-button variant="primary" class="btn-coral">
                    {{ __('Delete') }}
                </x-ts-button>
            </x-ts-modal-trigger>
            <x-ts-modal-trigger name="edit">
                <x-ts-button variant="primary" class="btn-ink">{{ __('Edit') }}</x-ts-button>
            </x-ts-modal-trigger>
        </div>
    </div>

    <div class="mt-6 mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
        @foreach ($this->stats as $stat)
            <div class="relative rounded-lg bg-zinc-50 px-6 py-4 dark:bg-zinc-700">
                <x-ts-subheading>{{ $stat['title'] }}</x-ts-subheading>
                <x-ts-heading size="xl" class="mb-2">{{ $stat['value'] }}</x-ts-heading>
            </div>
        @endforeach
    </div>

    <div class="mt-12">
        <x-ts-heading level="2">{{ __('Customer Details') }}</x-ts-heading>
        <x-ts-separator class="mt-4" />
        <x-description.list>
            <x-description.term>{{ __('Name') }}</x-description.term>
            <x-description.details>{{ $customer->name }}</x-description.details>
            <x-description.term>{{ __('Email') }}</x-description.term>
            <x-description.details>
                @if ($customer->email)
                    <x-ts-link href="mailto:{{ $customer->email }}">{{ $customer->email }}</x-ts-link>
                @else
                    &mdash;
                @endif
            </x-description.details>
            <x-description.term>{{ __('Phone') }}</x-description.term>
            <x-description.details>
                <x-ts-link
                    href="https://wa.me/{{ $customer->formatted_whatsapp_phone }}"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    {{ $customer->phone }}
                </x-ts-link>
            </x-description.details>
            <x-description.term>{{ __('Birthdate') }}</x-description.term>
            <x-description.details>{{ $customer->formatted_birthdate }}</x-description.details>
            <x-description.term>{{ __('Age') }}</x-description.term>
            <x-description.details>
                @if ($customer->age !== null)
                    {{ $customer->age }}
                @else
                    &mdash;
                @endif
            </x-description.details>
            <x-description.term>{{ __('Notes') }}</x-description.term>
            <x-description.details>
                @if ($editingNotes)
                    <div class="flex flex-col gap-2">
                        <x-ts-field>
                            <x-ts-textarea wire:model="editedNotes" rows="3" placeholder="{{ __('Notes') }}" />

                        </x-ts-field>
                        <div class="flex gap-2">
                            <x-ts-button wire:click="saveNotes" variant="primary" size="sm">
                                {{ __('Save') }}
                            </x-ts-button>
                            <x-ts-button wire:click="cancelEditingNotes" variant="subtle" size="sm">
                                {{ __('Cancel') }}
                            </x-ts-button>
                        </div>
                    </div>
                @else
                    <div class="flex items-start gap-2">
                        @if (empty($customer->notes))
                            <x-ts-text>{{ __('No notes yet') }}</x-ts-text>
                        @else
                            <div class="prose prose-sm dark:prose-invert">{!! $customer->formatted_notes !!}</div>
                        @endif
                        <x-ts-button
                            wire:click="startEditingNotes"
                            icon="pencil"
                            icon:variant="micro"
                            variant="subtle"
                            size="sm"
                            square
                            class="shrink-0"
                            inset="top"
                        />
                    </div>
                @endif
            </x-description.details>
        </x-description.list>
    </div>

    @if ($customer->photoshoots->isNotEmpty())
        <x-data-table class="mt-12">
            <x-data-table.columns>
                <x-data-table.column>{{ __('Photoshoot') }}</x-data-table.column>
                <x-data-table.column>{{ __('Location') }}</x-data-table.column>
            </x-data-table.columns>
            <x-data-table.rows>
                @foreach ($customer->photoshoots as $photoshoot)
                    <x-data-table.row>
                        <x-data-table.cell variant="strong" class="relative">
                            <a
                                href="/photoshoots/{{ $photoshoot->id }}"
                                wire:navigate
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            <p>{{ $photoshoot->name }}</p>
                        </x-data-table.cell>
                        <x-data-table.cell class="relative">
                            <a
                                href="/photoshoots/{{ $photoshoot->id }}"
                                wire:navigate
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            <p>{{ $photoshoot->formatted_date }}, {{ $photoshoot->location }}</p>
                        </x-data-table.cell>
                    </x-data-table.row>
                @endforeach
            </x-data-table.rows>
        </x-data-table>
    @endif

    @if ($this->galleries->isNotEmpty())
        <div class="mt-12">
            <x-ts-heading level="2">{{ __('Galleries') }}</x-ts-heading>
            <x-ts-separator class="mt-4" />
        </div>

        <div class="mt-12">
            <div
                class="grid grid-flow-dense auto-rows-[263px] grid-cols-[repeat(auto-fill,minmax(300px,1fr))] gap-x-4 gap-y-6"
            >
                @foreach ($this->galleries as $gallery)
                    <div
                        class="relative flex overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 dark:border-white/10 dark:bg-white/10"
                    >
                        <a class="flex w-full" href="/galleries/{{ $gallery->id }}" wire:navigate>
                            <img
                                src="{{ $gallery->photos()->first()?->thumbnail_url }}"
                                alt=""
                                class="mx-auto object-contain"
                            />
                        </a>
                        <div
                            class="absolute inset-x-0 bottom-0 flex gap-2 border-t border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900"
                        >
                            <x-ts-heading>{{ $gallery->name }}</x-ts-heading>
                            <x-ts-text>
                                {{ $gallery->photos()->count() }}
                                {{ $gallery->photos()->count() === 1 ? __('photo') : __('photos') }} •
                                {{ $gallery->getFormattedStorageSize() }} •
                                {{ $gallery->created_at->format('M j, Y') }}
                            </x-ts-text>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($this->contracts->isNotEmpty())
        <x-data-table class="mt-12">
            <x-data-table.columns>
                <x-data-table.column class="w-full">{{ __('Contract') }}</x-data-table.column>
                <x-data-table.column>{{ __('Location') }}</x-data-table.column>
                <x-data-table.column>{{ __('Shooting date') }}</x-data-table.column>
                <x-data-table.column>{{ __('Signatures') }}</x-data-table.column>
            </x-data-table.columns>
            <x-data-table.rows>
                @foreach ($this->contracts as $contract)
                    <x-data-table.row>
                        <x-data-table.cell variant="strong" class="relative">
                            <a
                                href="{{ route('contracts.show', ['contract' => $contract]) }}"
                                wire:navigate
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            <div class="flex items-end gap-2">
                                {{ $contract->title }}

                                @if ($contract->isExecuted())
                                    <x-ts-badge color="lime" size="sm">{{ __('Executed') }}</x-ts-badge>
                                @else
                                    <x-ts-badge size="sm">{{ __('Waiting signatures') }}</x-ts-badge>
                                @endif
                            </div>
                            <x-ts-text>{{ $contract->description }}</x-ts-text>
                        </x-data-table.cell>
                        <x-data-table.cell class="relative">
                            <a
                                href="{{ route('contracts.show', ['contract' => $contract]) }}"
                                wire:navigate
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            {{ $contract->location }}
                        </x-data-table.cell>
                        <x-data-table.cell class="relative">
                            <a
                                href="{{ route('contracts.show', ['contract' => $contract]) }}"
                                wire:navigate
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            {{ $contract->formatted_shooting_date }}
                        </x-data-table.cell>
                        <x-data-table.cell class="relative">
                            <a
                                href="{{ route('contracts.show', ['contract' => $contract]) }}"
                                wire:navigate
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            {{ $contract->signatures()->signed()->count() }}/{{ $contract->signatures()->count() }}
                        </x-data-table.cell>
                    </x-data-table.row>
                @endforeach
            </x-data-table.rows>
        </x-data-table>
    @endif

    @if ($this->payments->isNotEmpty())
        <x-data-table class="mt-12">
            <x-data-table.columns>
                <x-data-table.column>{{ __('Payments') }}</x-data-table.column>
                <x-data-table.column>{{ __('Amount') }}</x-data-table.column>
                <x-data-table.column>{{ __('Payment Date') }}</x-data-table.column>
                <x-data-table.column>{{ __('Customer Email') }}</x-data-table.column>
            </x-data-table.columns>
            <x-data-table.rows>
                @foreach ($this->payments as $payment)
                    <x-data-table.row>
                        <x-data-table.cell>{{ $payment->description }}</x-data-table.cell>
                        <x-data-table.cell>{{ $payment->formattedAmount }}</x-data-table.cell>
                        <x-data-table.cell>
                            {{ $payment->completed_at ? $payment->completed_at->format('F j, Y H:i') : '-' }}
                        </x-data-table.cell>
                        <x-data-table.cell>{{ $payment->customer_email }}</x-data-table.cell>
                    </x-data-table.row>
                @endforeach
            </x-data-table.rows>
        </x-data-table>
    @endif

    <div class="mt-12">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <x-ts-heading level="2">{{ __('Invoices') }}</x-ts-heading>

            <x-ts-button
                variant="primary"
                class="btn-ink"
                icon="document-plus"
                :href="route('invoices.create', ['customer' => $customer->ulid])"
                wire:navigate
            >
                {{ __('Create invoice') }}
            </x-ts-button>
        </div>
        <x-ts-separator class="mt-4" />
    </div>

    @if ($this->invoices->isNotEmpty())
        <x-data-table class="mt-6 [&_table]:table-auto [&_td:first-child]:pl-3 [&_td:last-child]:pr-3 [&_th:first-child]:pl-3 [&_th:last-child]:pr-3">
            <x-data-table.columns class="border-b border-zinc-200 bg-zinc-50 dark:border-white/10 dark:bg-white/5">
                <x-data-table.column>{{ __('Number') }}</x-data-table.column>
                <x-data-table.column class="hidden xl:table-cell">{{ __('Issue date') }}</x-data-table.column>
                <x-data-table.column class="group/right-align">{{ __('Total') }}</x-data-table.column>
                <x-data-table.column class="group/right-align hidden xl:table-cell">{{ __('Status') }}</x-data-table.column>
            </x-data-table.columns>
            <x-data-table.rows>
                @foreach ($this->invoices as $invoice)
                    <x-data-table.row>
                        <x-data-table.cell variant="strong" class="relative whitespace-nowrap">
                            <a
                                href="{{ route('invoices.show', $invoice) }}"
                                wire:navigate
                                class="absolute inset-0 focus:outline-hidden"
                            ></a>
                            <div class="flex flex-col">
                                <span class="font-mono">{{ $invoice->full_number ?? __('Draft') }}</span>
                                <span class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-normal text-zinc-500 xl:hidden">
                                    @if ($invoice->issue_date)
                                        <span>{{ $invoice->issue_date->format('Y-m-d') }}</span>
                                    @endif
                                    <x-ts-badge
                                        size="sm"
                                        :color="match ($invoice->status) {
                                            'issued' => 'green',
                                            'rectified' => 'orange',
                                            default => 'zinc',
                                        }"
                                        inset
                                    >
                                        {{ $invoice->status_label }}
                                    </x-ts-badge>
                                </span>
                            </div>
                        </x-data-table.cell>
                        <x-data-table.cell variant="muted" class="hidden whitespace-nowrap xl:table-cell">{{ $invoice->issue_date?->format('Y-m-d') ?? '—' }}</x-data-table.cell>
                        <x-data-table.cell class="whitespace-nowrap text-end">
                            {{ $this->money($invoice->total) }} {{ $invoice->currency }}
                        </x-data-table.cell>
                        <x-data-table.cell class="hidden text-end xl:table-cell">
                            <x-ts-badge
                                :color="match ($invoice->status) {
                                    'issued' => 'green',
                                    'rectified' => 'orange',
                                    default => 'zinc',
                                }"
                                inset
                            >
                                {{ $invoice->status_label }}
                            </x-ts-badge>
                        </x-data-table.cell>
                    </x-data-table.row>
                @endforeach
            </x-data-table.rows>
        </x-data-table>
    @else
        <p class="mt-4 text-sm text-zinc-500">{{ __('This client has no invoices yet.') }}</p>
    @endif

    <x-ts-modal name="edit" class="w-full sm:max-w-lg">
        <form wire:submit="update" class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Edit customer') }}</x-ts-heading>
                <x-ts-subheading>{{ __('Please enter your customer details.') }}</x-ts-subheading>
            </div>
            <x-customer-form-fields />
            <div class="flex">
                <x-ts-spacer />
                <x-ts-button type="submit" variant="primary">{{ __('Save') }}</x-ts-button>
            </div>
        </form>
    </x-ts-modal>

    @if ($this->sharedGalleriesCount > 0)
        <x-ts-modal name="client-link" class="w-full sm:max-w-lg">
            <div class="space-y-6">
                <x-ts-heading size="lg">{{ __('Client link') }}</x-ts-heading>

                <x-ts-input
                    icon="link"
                    :value="route('clients.show', $customer)"
                    :label="__('Share URL')"
                    readonly
                    copyable
                />

                <x-ts-text variant="subtle">
                    {{ __('Share this link with your client so they can view all their shared galleries in one place.') }}
                </x-ts-text>

                <x-ts-text variant="subtle">
                    {{ __('Shared galleries: :count', ['count' => $this->sharedGalleriesCount]) }}
                </x-ts-text>

                <x-ts-text variant="subtle">
                    {{ __('Remember that the link only shows shared galleries without a password.') }}
                </x-ts-text>
            </div>
        </x-ts-modal>
    @endif

    <x-confirm-modal
        name="delete-customer"
        :title="__('Delete customer?')"
        :message="__('This action cannot be undone.')"
        method="delete"
    />
</div>
