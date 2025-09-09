{{-- Team branding section with logo, name, and bio --}}
<div class="space-y-4 text-center">
    <a href="{{ route('handle.show', ['handle' => $team->handle]) }}" class="block space-y-4" wire:navigate>
        @if($team->brand_logo_icon_url)
            <img src="{{ $team->brand_logo_icon_url . '&w=256&h=256' }}" class="mx-auto size-32" alt="{{ $team->name }}" />
        @endif

        <flux:heading size="xl">{{ $team->name }}</flux:heading>
    </a>

    @if($team->bio)
        <div class="prose prose-sm max-w-none dark:prose-invert">
            {!! $team->bio !!}
        </div>
    @endif
</div>
