<?php

use App\Livewire\Forms\BioLinkForm;
use App\Models\BioLink;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('bio-links');

middleware(['auth', 'verified']);

new class extends Component
{
    public BioLinkForm $addForm;
    public BioLinkForm $editForm;

    public ?BioLink $editingLink = null;

    public function addLink()
    {
        $this->addForm->store();
        $this->addForm->resetForm();
    }

    public function updateLink(BioLink $link)
    {
        $this->authorize('update', $link);

        $this->editForm->update();

        $this->editForm->resetForm();
        $this->editingLink = null;
    }

    public function deleteLink(BioLink $link)
    {
        $this->authorize('delete', $link);

        $link->delete();
    }

    public function reorderLinks($links)
    {
        foreach ($links as $linkData) {
            $this->team->bioLinks()
                ->where('id', $linkData['id'])
                ->update(['order' => $linkData['order']]);
        }
    }

    public function editLink(BioLink $link)
    {
        $this->authorize('view', $link);

        $this->editingLink = $link;
        $this->editForm->setBioLink($link);
    }

    public function cancelEdit()
    {
        $this->editForm->resetForm();
        $this->editingLink = null;
    }

    #[Computed]
    public function team()
    {
        return Auth::user()->currentTeam;
    }

    #[Computed]
    public function bioLinks()
    {
        return $this->team?->bioLinks ?? collect();
    }
}; ?>

<x-app-layout>
    @volt('pages.bio-links')
        <div class="max-w-3xl mx-auto">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="max-sm:w-full sm:flex-1">
                    <x-heading level="1" size="xl">{{ __('Bio Links') }}</x-heading>
                    <x-subheading>{{ __('Manage your bio links for your public profile.') }}</x-subheading>
                </div>
            </div>

            <div class="mt-8">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="w-1/2">{{ __('Title') }}</flux:table.column>
                        <flux:table.column class="w-1/2">{{ __('URL') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        <!-- Existing Links -->
                        @foreach ($this->bioLinks as $link)
                            <flux:table.row :key="$link->id">
                                @if ($editingLink && $editingLink->id === $link->id)
                                    <flux:table.cell>
                                        <flux:field>
                                            <flux:input wire:model="editForm.title" type="text" class="ml-1" />

                                            <flux:error name="editForm.title" />
                                        </flux:field>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:field>
                                            <flux:input wire:model="editForm.url" type="url" />
                                            <flux:error name="editForm.url" />
                                        </flux:field>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex gap-2">
                                            <flux:button wire:click="updateLink({{ $editingLink }})" icon="check" variant="primary" size="sm" color="green" />
                                            <flux:button wire:click="cancelEdit" wire:key="'cancel-'.$link->id" icon="x-mark" variant="subtle" size="sm" />
                                        </div>
                                    </flux:table.cell>
                                @else
                                    <flux:table.cell variant="strong">
                                        {{ $link->title }}
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        {{ $link->url }}
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex gap-2">
                                            <flux:button wire:click="editLink({{ $link->id }})" icon="pencil-square" variant="subtle" size="sm" />
                                            <flux:button
                                                wire:click="deleteLink({{ $link->id }})"
                                                wire:confirm="Are you sure you want to delete this bio link?"
                                                icon="trash"
                                                variant="subtle"
                                                size="sm"
                                            >
                                            </flux:button>
                                        </div>
                                    </flux:table.cell>
                                @endif
                            </flux:table.row>
                        @endforeach

                        <flux:table.row>
                            <flux:table.cell>
                                <flux:field>
                                    <flux:input
                                        wire:model="addForm.title"
                                        type="text"
                                        placeholder="e.g. Instagram"
                                        class="ml-1 min-w-40"
                                    />

                                    <flux:error name="addForm.title" />
                                </flux:field>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:field>
                                    <flux:input
                                        wire:model="addForm.url"
                                        type="url"
                                        class="min-w-40"
                                        placeholder="https://instagram.com/username"
                                    />

                                    <flux:error name="addForm.url" />
                                </flux:field>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button wire:click="addLink" icon="plus" variant="primary" size="sm" />
                            </flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>
            </div>
        </div>
    @endvolt
</x-app-layout>
