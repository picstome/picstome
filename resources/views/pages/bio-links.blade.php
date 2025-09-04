<?php

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
    public string $title = '';
    public string $url = '';
    public ?int $editingLink = null;

    protected $rules = [
        'title' => 'required|string|max:255',
        'url' => 'required|url',
    ];

    public function addLink()
    {
        $this->validate();

        $this->currentTeam->bioLinks()->create([
            'title' => $this->title,
            'url' => $this->url,
        ]);

        $this->reset(['title', 'url']);
    }

    public function updateLink()
    {
        $this->validate();

        $link = $this->currentTeam->bioLinks()->find($this->editingLink);
        if ($link) {
            $link->update([
                'title' => $this->title,
                'url' => $this->url,
            ]);
        }

        $this->reset(['title', 'url', 'editingLink']);
    }

    public function deleteLink($linkId)
    {
        $link = $this->currentTeam->bioLinks()->find($linkId);
        if ($link) {
            $link->delete();
        }
    }

    public function reorderLinks($links)
    {
        foreach ($links as $linkData) {
            $this->currentTeam->bioLinks()
                ->where('id', $linkData['id'])
                ->update(['order' => $linkData['order']]);
        }
    }

    public function editLink($linkId)
    {
        $link = $this->currentTeam->bioLinks()->find($linkId);
        if ($link) {
            $this->editingLink = $link->id;
            $this->title = $link->title;
            $this->url = $link->url;
        }
    }

    public function cancelEdit()
    {
        $this->reset(['title', 'url', 'editingLink']);
    }

    #[Computed]
    public function currentTeam()
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        if ($user->current_team_id) {
            return $user->currentTeam;
        }

        // Find personal team
        $personalTeam = $user->ownedTeams()->where('personal_team', true)->first();
        if ($personalTeam) {
            $user->current_team_id = $personalTeam->id;
            $user->save();
            return $personalTeam;
        }

        return null;
    }

    #[Computed]
    public function bioLinks()
    {
        return $this->currentTeam?->bioLinks ?? collect();
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

            <div class="mt-8 grid gap-8 lg:grid-cols-2">
                <!-- Add/Edit Form -->
                <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-white/10 dark:bg-white/5">
                    <flux:heading size="md" class="mb-4">
                        {{ $editingLink ? __('Edit Bio Link') : __('Add New Bio Link') }}
                    </flux:heading>

                    <form wire:submit="{{ $editingLink ? 'updateLink' : 'addLink' }}" class="space-y-4">
                        <flux:input
                            wire:model="title"
                            :label="__('Title')"
                            type="text"
                            placeholder="e.g. Instagram"
                        />

                        <flux:input
                            wire:model="url"
                            :label="__('URL')"
                            type="url"
                            placeholder="https://instagram.com/username"
                        />

                        <div class="flex gap-2">
                            @if ($editingLink)
                                <flux:button type="submit" variant="primary">
                                    {{ __('Update Link') }}
                                </flux:button>
                                <flux:button wire:click="cancelEdit" variant="ghost">
                                    {{ __('Cancel') }}
                                </flux:button>
                            @else
                                <flux:button type="submit" variant="primary">
                                    {{ __('Add Link') }}
                                </flux:button>
                            @endif
                        </div>
                    </form>
                </div>

                <!-- Links List -->
                <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-white/10 dark:bg-white/5">
                    <flux:heading size="md" class="mb-4">{{ __('Your Bio Links') }}</flux:heading>

                    @if ($this->bioLinks->isNotEmpty())
                        <div class="space-y-3">
                            @foreach ($this->bioLinks as $link)
                                <div class="flex items-center justify-between rounded border border-zinc-200 p-3 dark:border-white/10">
                                    <div class="flex-1">
                                        <div class="font-medium">{{ $link->title }}</div>
                                        <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $link->url }}</div>
                                    </div>
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
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                            {{ __('No bio links yet. Add your first link above.') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endvolt
</x-app-layout>