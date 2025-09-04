<x-guest-layout :font="$team->brand_font">
    <div class="flex min-h-screen items-center justify-center">
        <div class="mx-auto w-full max-w-md text-center">
            <div class="space-y-4">
                @if($team->brand_logo_icon_url)
                    <flux:avatar size="xl" :src="$team->brand_logo_icon_url" class="mx-auto" />
                @endif

                <flux:heading size="xl">{{ $team->name }}</flux:heading>
            </div>

            @if($team->bioLinks->isNotEmpty())
                <div class="my-8">
                    <div class="space-y-3">
                        @foreach($team->bioLinks as $link)
                            <flux:button
                                variant="primary"
                                :color="$team->brand_color ?? null"
                                href="{{ $link->url }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="w-full text-base!"
                            >{{ $link->title }}</flux:button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-guest-layout>
