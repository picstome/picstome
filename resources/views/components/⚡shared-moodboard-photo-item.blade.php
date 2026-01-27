<?php

use App\Models\MoodboardPhoto;
use Livewire\Component;

new class extends Component
{
    public MoodboardPhoto $photo;

    public ?string $htmlId = null;
}; ?>

<div class="group relative flex aspect-square overflow-hidden bg-zinc-100 dark:bg-white/10">
    <a id="{{ $htmlId }}" class="mx-auto flex w-full">
        @if ($photo->isImage())
            <img
                x-data="{ loaded: false, errored: false }"
                x-init="if ($el.complete) loaded = true"
                src="{{ $photo->url }}"
                alt=""
                @contextmenu.prevent
                x-on:load="loaded = true"
                x-on:error="errored = true"
                class="h-full w-full bg-zinc-300 object-cover dark:bg-white/10"
                :class="loaded || errored ? '' : 'animate-pulse '"
                loading="lazy"
            />
        @elseif ($photo->isVideo())
            <video class="h-full w-full bg-zinc-300 object-cover dark:bg-white/10" muted>
                <source src="{{ $photo->url }}" type="video/{{ pathinfo($photo->path, PATHINFO_EXTENSION) }}" />
                Your browser does not support video tag.
            </video>
        @else
            <div class="h-full w-full animate-pulse bg-zinc-300 dark:bg-white/10"></div>
        @endif
    </a>
</div>
