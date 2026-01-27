<?php

use App\Models\Moodboard;
use App\Models\MoodboardPhoto;
use Livewire\Component;

new class extends Component
{
    public MoodboardPhoto $photo;

    public Moodboard $moodboard;

    public ?string $htmlId = null;

    public function mount()
    {
        $this->moodboard = $this->photo->moodboard;
    }

    public function delete()
    {
        $this->authorize('delete', $this->photo);

        $this->photo->deleteFromDisk()->delete();

        $this->dispatch('moodboard-photo-deleted');
    }
}; ?>

<div
    class="group relative flex aspect-square overflow-hidden bg-zinc-100 dark:bg-white/10"
    x-data="{
        showActions: false,
        moreActionsOpen: false,
    }"
    @mouseenter="showActions = true"
    @mouseleave="if (!moreActionsOpen) showActions = false"
>
    <div class="mx-auto flex w-full">
        @if ($photo->isImage())
            @if ($photo->url)
                <img
                    x-data="{ loaded: false, errored: false }"
                    x-init="if ($el.complete) loaded = true"
                    src="{{ $photo->url }}"
                    alt=""
                    x-on:load="loaded = true"
                    x-on:error="errored = true"
                    class="h-full w-full bg-zinc-300 object-cover dark:bg-white/10"
                    :class="loaded || errored ? '' : 'animate-pulse '"
                    loading="lazy"
                />
            @else
                <div class="h-full w-full animate-pulse bg-zinc-300 dark:bg-white/10"></div>
            @endif
        @elseif ($photo->isVideo())
            <video class="h-full w-full bg-zinc-300 object-cover dark:bg-white/10" muted>
                <source src="{{ $photo->url }}" type="video/{{ pathinfo($photo->path, PATHINFO_EXTENSION) }}" />
                Your browser does not support the video tag.
            </video>
        @else
            <div class="h-full w-full animate-pulse bg-zinc-300 dark:bg-white/10"></div>
        @endif
    </div>

    <div class="absolute right-1.5 bottom-1.5 flex flex-row-reverse gap-2" :class="showActions ? 'flex' : 'hidden'">
        <flux:dropdown x-model="moreActionsOpen">
            <flux:button icon="ellipsis-vertical" square size="sm" />
            <flux:menu>
                <flux:menu.item
                    wire:click="delete"
                    wire:confirm="{{ __('Are you sure?') }}"
                    icon="trash"
                    variant="danger"
                >
                    {{ __('Delete') }}
                </flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </div>
</div>
