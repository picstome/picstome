<?php

use App\Traits\InteractsWithTallStackModals;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    use InteractsWithTallStackModals;
    public Contract $contract;

    public ?Collection $photoshoots = null;

    public ?int $photoshoot_id = null;

    public function mount()
    {
        $this->authorize('view', $this->contract);

        $this->photoshoots = Auth::user()?->currentTeam->photoshoots()->latest()->get();
        $this->photoshoot_id = $this->contract->photoshoot_id;
    }

    public function execute()
    {
        abort_if($this->contract->signaturesRemaining() > 0, 401);

        $this->contract->execute();
    }

    public function download()
    {
        if ($this->contract->isExecuted()) {
            return $this->contract->download();
        }
    }

    public function delete()
    {
        $this->contract
            ->deleteFromDisk()
            ->deleteSignatures()
            ->delete();

        return $this->redirect(route('contracts'));
    }

    public function assignToPhotoshoot()
    {
        $this->validate([
            'photoshoot_id' => [
                'nullable',
                Rule::exists('photoshoots', 'id')->where(
                    fn ($query) => $query->where('team_id', Auth::user()->currentTeam->id)
                ),
            ],
        ]);

        $this->contract->update([
            'photoshoot_id' => $this->photoshoot_id,
        ]);

        $this->closeModal('assign-photoshoot');
    }
}; ?>

<div @focus.window="$wire.$refresh()">
    <div class="max-lg:hidden">
        <x-ts-button :href="route('contracts')" icon="chevron-left" variant="subtle" inset>
            {{ __('Contracts') }}
        </x-ts-button>
    </div>

    <div class="mt-4 lg:mt-8">
        <div class="flex items-center gap-4">
            <x-ts-heading level="1" size="xl">{{ $contract->title }}</x-ts-heading>
            @if ($contract->isExecuted())
                <x-ts-badge color="lime" size="sm">{{ __('Executed') }}</x-ts-badge>
            @endif
        </div>
        <x-ts-subheading class="mt-2">{{ $contract->description }}</x-ts-subheading>
        <div class="isolate mt-2.5 flex flex-wrap justify-between gap-x-6 gap-y-4">
            <div class="flex flex-wrap gap-x-10 gap-y-4 py-1.5">
                <span class="flex items-center gap-3 text-sm text-zinc-800 dark:text-white">
                    <x-icon name="pencil-square"
                        variant="solid"
                        class="size-4 shrink-0 fill-zinc-400 dark:fill-zinc-500"
                    />
                    <span>
                        {{ $contract->signatures()->signed()->count() }}/{{ $contract->signatures()->count() }}
                        {{ __('signatures') }}
                    </span>
                </span>

                <span class="flex items-center gap-3 text-sm text-zinc-800 dark:text-white">
                    <x-icon name="calendar"
                        variant="solid"
                        class="size-4 shrink-0 fill-zinc-400 dark:fill-zinc-500"
                    />
                    <span>{{ $contract->formatted_shooting_date }}</span>
                </span>
            </div>
            <div class="flex gap-4">
                <x-dropdown position="bottom-end">
                    <x-slot:action>
                        <x-ts-button icon="ellipsis-horizontal" variant="ghost" square x-on:click="show = ! show" />
                    </x-slot:action>

                    <x-dropdown.items
                        :text="__('Assign to photoshoot')"
                        icon="camera"
                        x-on:click="show = false; $tsui.open.modal('assign-photoshoot')"
                    />
                    <x-dropdown.items
                        :text="__('Delete contract')"
                        icon="trash"
                        x-on:click="show = false; $tsui.open.modal('delete-contract')"
                        class="text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                    />
                </x-dropdown>

                @if ($contract->isExecuted() && $contract->pdf_file_path)
                    <x-ts-button wire:click="download" variant="primary">{{ __('Download') }}</x-ts-button>
                @endif

                @if (! $contract->isExecuted() && $contract->signaturesRemaining() === 0)
                    <x-ts-button wire:click="execute" variant="primary">{{ __('Execute') }}</x-ts-button>
                @endif
            </div>
        </div>
    </div>

    <div class="mt-12">
        <x-ts-heading level="2">{{ __('Summary') }}</x-ts-heading>
        <x-ts-separator class="mt-4" />
        <x-description.list>
            <x-description.term>{{ __('Location') }}</x-description.term>
            <x-description.details>{{ $contract->location }}</x-description.details>

            <x-description.term>{{ __('Shooting date') }}</x-description.term>
            <x-description.details>{{ $contract->formatted_shooting_date }}</x-description.details>

            <x-description.term>{{ __('Signatures required') }}</x-description.term>
            <x-description.details>
                {{ $contract->signatures()->signed()->count() }}/{{ $contract->signatures()->count() }}
            </x-description.details>
        </x-description.list>
    </div>

    <x-ts-heading level="2" class="mt-12">{{ __('Signatures') }}</x-ts-heading>
    <x-data-table class="mt-4">
        <x-data-table.columns>
            <x-data-table.column></x-data-table.column>
            <x-data-table.column>
                <span class="text-zinc-500 dark:text-zinc-300">
                    {{ __('Signature number') }}
                </span>
            </x-data-table.column>
            <x-data-table.column class="w-full">
                <span class="text-zinc-500 dark:text-zinc-300">{{ __('Name') }}</span>
            </x-data-table.column>
            <x-data-table.column>
                <span class="text-zinc-500 dark:text-zinc-300">{{ __('Signed at') }}</span>
            </x-data-table.column>
        </x-data-table.columns>

        <x-data-table.rows>
            @foreach ($contract->signatures as $signature)
                <x-data-table.row>
                    <x-data-table.cell>
                        @if ($signature->isSigned())
                            <x-ts-button
                                href="{{ $signature->signature_image_url }}"
                                target="_blank"
                                size="sm"
                            >
                                {{ __('View signature') }}
                            </x-ts-button>
                        @else
                            <x-ts-button
                                size="sm"
                                :href="route('signatures.sign', ['signature' => $signature])"
                                target="_blank"
                            >
                                {{ __('Sign') }}
                            </x-ts-button>
                        @endif
                    </x-data-table.cell>
                    <x-data-table.cell>
                        <span class="font-mono">{{ $signature->ulid }}</span>

                        @if ($signature->isSigned())
                            <x-ts-badge size="sm" color="lime">{{ __('Signed') }}</x-ts-badge>
                        @else
                            <x-ts-badge size="sm">{{ __('Unsigned') }}</x-ts-badge>
                        @endif
                    </x-data-table.cell>
                    <x-data-table.cell>
                        {{ $signature->legal_name }}
                        <br />
                        {{ $signature->email }}
                    </x-data-table.cell>
                    <x-data-table.cell>{{ $signature->formatted_signed_at }}</x-data-table.cell>
                </x-data-table.row>
            @endforeach
        </x-data-table.rows>
    </x-data-table>

    <div class="mt-12">
        <x-ts-heading level="2">{{ __('Contract terms') }}</x-ts-heading>
        <x-ts-separator class="mt-4" />
        <x-description.list>
            <x-description.term>{{ __('Terms') }}</x-description.term>
            <x-description.details>
                <div
                    class="prose prose-sm dark:prose-invert flex lg:min-h-[1123px] lg:min-w-[794px] flex-col items-stretch justify-start overflow-auto lg:border lg:border-zinc-200 lg:p-6 lg:shadow-sm dark:border-zinc-700"
                >
                    {!! $contract->formatted_markdown_body !!}
                </div>
            </x-description.details>
        </x-description.list>
    </div>

    <x-ts-modal name="assign-photoshoot" class="md:w-96">
        <form wire:submit="assignToPhotoshoot" class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Assign to Photoshoot') }}</x-ts-heading>
                <x-ts-text class="mt-2">{{ __('Select a photoshoot to assign this contract.') }}</x-ts-text>
            </div>
            <x-ts-select wire:model="photoshoot_id" placeholder="{{ __('Choose photoshoot...') }}">
                <x-ts-select-option value="">{{ __('No photoshoot') }}</x-ts-select-option>
                <hr />
                @if ($photoshoots)
                    @foreach ($photoshoots as $photoshoot)
                        <x-ts-select-option value="{{ $photoshoot->id }}">
                            {{ $photoshoot->name }}
                        </x-ts-select-option>
                    @endforeach
                @endif
            </x-ts-select>
            <div class="flex gap-2">
                <x-ts-spacer />
                <x-ts-modal-close>
                    <x-ts-button variant="ghost">{{ __('Cancel') }}</x-ts-button>
                </x-ts-modal-close>
                <x-ts-button wire:click="assignToPhotoshoot" variant="primary">
                    {{ __('Assign') }}
                </x-ts-button>
            </div>
        </form>
    </x-ts-modal>

    <x-confirm-modal
        name="delete-contract"
        :title="__('Delete contract?')"
        :message="__('This action cannot be undone.')"
        :confirm-label="__('Delete contract')"
        method="delete"
    />
</div>
