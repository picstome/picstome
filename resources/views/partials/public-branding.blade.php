{{-- Team branding section with logo, name, and bio --}}
<div class="space-y-4">
    <a href="{{ route('handle.show', ['handle' => $team->handle]) }}" class="block py-4" wire:navigate>
        @if($team->brand_logo_icon_url)
            <img src="{{ $team->brand_logo_icon_url . '&w=256&h=256' }}" class="mx-auto size-32" alt="{{ $team->name }}" />
        @endif

        <flux:heading size="xl">{{ $team->name }}</flux:heading>
    </a>

    @if($team->bio)
        <div class="mt-4 prose prose-sm max-w-none dark:prose-invert">
            {!! $team->bio !!}
        </div>
    @endif

    @if($team->galleries()->public()->exists())
        <div class="mt-6 flex justify-center">
            <flux:button
                variant="primary"
                :color="$team->brand_color ?? null"
                href="{{ route('portfolio.index', ['handle' => $team->handle]) }}"
                wire:navigate
            >
                View Portfolio
            </flux:button>
        </div>
    @endif
</div>