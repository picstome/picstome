<?php

use App\Models\Contract;
use App\Models\Photoshoot;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('contracts.show');

middleware(['auth', 'verified', 'can:view,contract']);

new class extends Component
{
    public Contract $contract;

    public ?Collection $photoshoots = null;

    public ?int $photoshoot_id = null;

    public function mount()
    {
        $this->photoshoots = Auth::user()?->currentTeam->photoshoots;
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

        return $this->redirect('/contracts');
    }

    public function assignToPhotoshoot()
    {
        $this->validate([
            'photoshoot_id' => [
                'nullable',
                Rule::exists('photoshoots', 'id')->where(
                    fn($query) => $query->where('team_id', Auth::user()->currentTeam->id)
                ),
            ],
        ]);

        $this->contract->update([
            'photoshoot_id' => $this->photoshoot_id
        ]);

        Flux::modal('assign-photoshoot')->close();
    }
}; ?>

<x-app-layout>
    @volt('pages.contracts.show')
        <div @focus.window="$wire.$refresh()">
            <div class="max-lg:hidden">
                <flux:button :href="route('contracts')" icon="chevron-left" variant="subtle" inset>
                    {{ __('Contracts') }}
                </flux:button>
            </div>

            <div class="mt-4 lg:mt-8">
                <div class="flex items-center gap-4">
                    <x-heading level="1" size="xl">{{ $contract->title }}</x-heading>
                    @if ($contract->isExecuted())
                        <flux:badge color="lime" size="sm">{{ __('Executed') }}</flux:badge>
                    @endif
                </div>
                <x-subheading class="mt-2">{{ $contract->description }}</x-subheading>
                <div class="isolate mt-2.5 flex flex-wrap justify-between gap-x-6 gap-y-4">
                    <div class="flex flex-wrap gap-x-10 gap-y-4 py-1.5">
                        <span class="flex items-center gap-3 text-sm text-zinc-800 dark:text-white">
                            <flux:icon.pencil-square
                                variant="solid"
                                class="size-4 shrink-0 fill-zinc-400 dark:fill-zinc-500"
                            />
                            <span>
                                {{ $contract->signatures()->signed()->count() }}/{{ $contract->signatures()->count() }}
                                {{ __('signatures') }}
                            </span>
                        </span>

                        <span class="flex items-center gap-3 text-sm text-zinc-800 dark:text-white">
                            <flux:icon.calendar
                                variant="solid"
                                class="size-4 shrink-0 fill-zinc-400 dark:fill-zinc-500"
                            />
                            <span>{{ $contract->formatted_shooting_date }}</span>
                        </span>
                    </div>
                    <div class="flex gap-4">
                        <flux:dropdown>
                            <flux:button icon="ellipsis-horizontal" variant="ghost" square />
                            <flux:menu>
                                <flux:modal.trigger name="assign-photoshoot">
                                    <flux:menu.item
                                        icon="camera"
                                    >
                                        {{ __('Assign to photoshoot') }}
                                    </flux:menu.item>
                                </flux:modal.trigger>
                                <flux:menu.item
                                    icon="trash"
                                    variant="danger"
                                    wire:click="delete"
                                    wire:confirm="{{ __('Are you sure?') }}"
                                >
                                    {{ __('Delete contract') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>

                        @if ($contract->isExecuted() && $contract->pdf_file_path)
                            <flux:button wire:click="download" variant="primary">{{ __('Download') }}</flux:button>
                        @endif

                        @if (! $contract->isExecuted() && $contract->signaturesRemaining() === 0)
                            <flux:button wire:click="execute" variant="primary">{{ __('Execute') }}</flux:button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-12">
                <flux:heading level="2">{{ __('Summary') }}</flux:heading>
                <flux:separator class="mt-4" />
                <x-description.list>
                    <x-description.term>{{ __('Location') }}</x-description.term>
                    <x-description.details>{{ $contract->location }}</x-description.details>

                    <x-description.term>{{ __('Shooting date') }}</x-description.term>
                    <x-description.details>{{ $contract->formatted_shooting_date }}</x-description.details>

                    <x-description.term>{{ __('Signatures required') }}</x-description.term>
                    <x-description.details>
                        {{ $contract->signatures()->signed()->count() }}/{{ $contract->signatures()->count() }}
                    </x-description.details>

                    <x-description.term>{{ __('Terms') }}</x-description.term>
                    <x-description.details>
                        <div class="prose prose-sm">{!! $contract->formatted_markdown_body !!}</div>
                    </x-description.details>
                </x-description.list>
            </div>

            <flux:heading level="2" class="mt-12">{{ __('Signatures') }}</flux:heading>
            <x-table class="mt-4">
                <x-table.columns>
                    <x-table.column>
                        <span class="text-zinc-500 dark:text-zinc-300">
                            {{ __('Signature number') }}
                        </span>
                    </x-table.column>
                    <x-table.column class="w-full">
                        <span class="text-zinc-500 dark:text-zinc-300">{{ __('Name') }}</span>
                    </x-table.column>
                    <x-table.column>
                        <span class="text-zinc-500 dark:text-zinc-300">{{ __('Signed at') }}</span>
                    </x-table.column>
                    <x-table.column></x-table.column>
                </x-table.columns>

                <x-table.rows>
                    @foreach ($contract->signatures as $signature)
                        <x-table.row>
                            <x-table.cell>
                                <span class="font-mono">{{ $signature->ulid }}</span>

                                @if ($signature->isSigned())
                                    <flux:badge size="sm" color="lime">{{ __('Signed') }}</flux:badge>
                                @else
                                    <flux:badge size="sm">{{ __('Unsigned') }}</flux:badge>
                                @endif
                            </x-table.cell>
                            <x-table.cell>
                                {{ $signature->legal_name }}
                                <br />
                                {{ $signature->email }}
                            </x-table.cell>
                            <x-table.cell>{{ $signature->formatted_signed_at }}</x-table.cell>
                            <x-table.cell>
                                @if ($signature->isSigned())
                                    <flux:button
                                        href="{{ $signature->signature_image_url }}"
                                        target="_blank"
                                        size="sm"
                                    >
                                        {{ __('View signature') }}
                                    </flux:button>
                                @else
                                    <flux:button
                                        size="sm"
                                        :href="route('signatures.sign', ['signature' => $signature])"
                                        target="_blank"
                                    >
                                        {{ __('Sign') }}
                                    </flux:button>
                                @endif
                            </x-table.cell>
                        </x-table.row>
                    @endforeach
                </x-table.rows>
            </x-table>

            <flux:modal name="assign-photoshoot" class="md:w-96">
                <form wire:submit="assignToPhotoshoot" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Assign to Photoshoot') }}</flux:heading>
                        <flux:text class="mt-2">{{ __('Select a photoshoot to assign this contract.') }}</flux:text>
                    </div>
                    <flux:select wire:model="photoshoot_id" placeholder="{{ __('Choose photoshoot...') }}">
                        <flux:select.option value="">{{ __('No photoshoot') }}</flux:select.option>
                        <hr />
                        @if ($photoshoots)
                            @foreach ($photoshoots as $photoshoot)
                                <flux:select.option value="{{ $photoshoot->id }}">
                                    {{ $photoshoot->name }}
                                </flux:select.option>
                            @endforeach
                        @endif
                    </flux:select>
                    <div class="flex gap-2">
                        <flux:spacer />
                        <flux:modal.close>
                            <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button wire:click="assignToPhotoshoot" variant="primary">
                            {{ __('Assign') }}
                        </flux:button>
                    </div>
                </form>
            </flux:modal>
        </div>
    @endvolt
</x-app-layout>
