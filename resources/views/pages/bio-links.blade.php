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
        <div>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="max-sm:w-full sm:flex-1">
                    <x-heading level="1" size="xl">{{ __('Bio Links') }}</x-heading>
                    <x-subheading>{{ __('Manage your bio links for your public profile.') }}</x-subheading>
                </div>
            </div>

            <div class="mt-8">
                <!-- Bio Links Table -->
                <flux:heading size="md" class="mb-4">{{ __('Your Bio Links') }}</flux:heading>

                <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Title') }}</flux:table.column>
                            <flux:table.column>{{ __('URL') }}</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>
                    <flux:table.rows>
                        <!-- Existing Links -->
                        @foreach ($this->bioLinks as $link)
                            <flux:table.row :key="$link->id">
                                @if ($editingLink && $editingLink->id === $link->id)
                                        <flux:table.cell>
                                            <flux:input
                                                wire:model="editForm.title"
                                                type="text"
                                                size="sm"
                                            />
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <flux:input
                                                wire:model="editForm.url"
                                                type="url"
                                                size="sm"
                                            />
                                        </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex gap-2">
                                            <flux:button wire:click="updateLink({{ $editingLink }})" variant="primary" size="sm">
                                                {{ __('Update') }}
                                            </flux:button>
                                            <flux:button wire:click="cancelEdit" variant="ghost" size="sm">
                                                {{ __('Cancel') }}
                                            </flux:button>
                                        </div>
                                    </flux:table.cell>
                                @else
                                    <flux:table.cell>
                                        <div class="font-medium">{{ $link->title }}</div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $link->url }}</div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex gap-2">
                                            <flux:button wire:click="editLink({{ $link->id }})" variant="ghost" size="sm">
                                                {{ __('Edit') }}
                                            </flux:button>
                                            <flux:button
                                                wire:click="deleteLink({{ $link->id }})"
                                                wire:confirm="Are you sure you want to delete this bio link?"
                                                variant="ghost"
                                                size="sm"
                                                color="red"
                                            >
                                                {{ __('Delete') }}
                                            </flux:button>
                                        </div>
                                    </flux:table.cell>
                                @endif
                            </flux:table.row>
                        @endforeach

                        <flux:table.row>
                            <flux:table.cell>
                                <flux:input
                                    wire:model="addForm.title"
                                    type="text"
                                    placeholder="e.g. Instagram"
                                    size="sm"
                                />
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:input
                                    wire:model="addForm.url"
                                    type="url"
                                    placeholder="https://instagram.com/username"
                                    size="sm"
                                />
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button wire:click="addLink" variant="primary" size="sm">
                                    {{ __('Add Link') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>
            </div>
        </div>
    @endvolt
</x-app-layout>
