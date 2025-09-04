<?php

use App\Models\BioLink;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('bio-links');

middleware(['auth', 'verified']);

new class extends Component
{
    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate('required|url')]
    public string $url = '';

    public ?BioLink $editingLink = null;

    public function addLink()
    {
        $this->validate();

        $this->team->bioLinks()->create([
            'title' => $this->title,
            'url' => $this->url,
        ]);

        $this->reset(['title', 'url']);
    }

    public function updateLink(BioLink $link)
    {
        $this->authorize('update', $link);

        $this->validate();

        $link->update([
            'title' => $this->title,
            'url' => $this->url,
        ]);

        $this->reset(['title', 'url', 'editingLink']);
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
        $this->title = $link->title;
        $this->url = $link->url;
    }

    public function cancelEdit()
    {
        $this->reset(['title', 'url', 'editingLink']);
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
                        <flux:table.column>{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        <!-- Existing Links -->
                        @foreach ($this->bioLinks as $link)
                            <flux:table.row :key="$link->id">
                                    @if ($editingLink && $editingLink->id === $link->id)
                                        <!-- Edit Mode -->
                                        <flux:table.cell>
                                            <flux:input
                                                wire:model="title"
                                                type="text"
                                                size="sm"
                                            />
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <flux:input
                                                wire:model="url"
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
                                    <!-- Display Mode -->
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

                        <!-- Add New Link Row -->
                        <flux:table.row>
                            <flux:table.cell>
                                <flux:input
                                    wire:model="title"
                                    type="text"
                                    placeholder="e.g. Instagram"
                                    size="sm"
                                />
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:input
                                    wire:model="url"
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
