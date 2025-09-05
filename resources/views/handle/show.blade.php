<x-guest-layout :font="$team->brand_font" :full-screen="true">
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="mx-auto w-full max-w-md text-center">
            <div class="space-y-4">
                @if($team->brand_logo_icon_url)
                    <flux:avatar size="xl" :src="$team->brand_logo_icon_url" class="mx-auto [:where(&)]:size-32" />
                @endif

                <flux:heading size="xl">{{ $team->name }}</flux:heading>

                @if($team->bio)
                    <div class="mt-4 prose prose-sm max-w-none">
                        {!! $team->bio !!}
                    </div>
                @endif
            </div>

            @if($team->bioLinks->isNotEmpty())
                <div class="my-14">
                    <div class="space-y-3">
                        @foreach($team->bioLinks as $link)
                            <flux:button
                                variant="primary"
                                :color="$team->brand_color ?? null"
                                href="{{ $link->url }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="w-full text-base!"
                            ><span class="truncate">{{ $link->title }}</span></flux:button>
                        @endforeach
                    </div>
                </div>
            @endif

            @unlesssubscribed($team)
                <div class="py-3">
                    @include('partials.powered-by')
                </div>
            @endsubscribed
        </div>
    </div>
</x-guest-layout>
