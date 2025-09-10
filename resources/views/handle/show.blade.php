<x-guest-layout :font="$team->brand_font" :full-screen="true" :title="$team->name">
    <x-slot name="head">
        @if($team->brand_logo_icon_url)
            <link rel="apple-touch-icon" sizes="300x300" href="{{ $team->brand_logo_icon_url . '&w=300&h=300' }}" />
            <link rel="icon" type="image/png" sizes="300x300" href="{{ $team->brand_logo_icon_url . '&w=300&h=300' }}" />
        @endif
        <meta name="twitter:card" content="summary" />
        <meta name="twitter:image" content="{{ $team->brand_logo_icon_url ? $team->brand_logo_icon_url . '&w=300&h=300' : '' }}" />
        <meta property="og:type" content="profile" />
        <meta property="og:url" content="{{ url()->current() }}" />
        <meta property="og:title" content="{{ $team->name }}" />
        <meta property="og:description" content="{{ $team->bio ?: $team->name }}" />
        <meta property="og:image" content="{{ $team->brand_logo_icon_url ? $team->brand_logo_icon_url . '&w=300&h=300' : '' }}" />
        @if(app()->environment('production'))
            @include('partials.google-analytics')
        @endif
    </x-slot>
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="mx-auto w-full max-w-md text-center">
            <div class="space-y-4">
                @include('partials.public-branding')
                @if($team->bio)
                    <div class="prose prose-sm max-w-none dark:prose-invert">
                        {!! $team->bio !!}
                    </div>
                @endif
                @if($team->galleries()->public()->exists())
                    <div class="flex justify-center">
                        <flux:button
                            variant="primary"
                            :color="$team->brand_color ?? null"
                            href="{{ route('portfolio.index', ['handle' => $team->handle]) }}"
                            wire:navigate
                        >
                             {{ __('View Portfolio') }}
                        </flux:button>
                    </div>
                @endif
            </div>

            @if($team->bioLinks->isNotEmpty())
                <div class="mt-7 mb-14">
                    <div class="space-y-3">
                        @foreach($team->bioLinks as $link)
                            <flux:button
                                variant="primary"
                                :color="$team->brand_color ?? null"
                                href="{{ $link->url }}"
                                target="_blank"
                                rel="noopener noreferrer nofollow"
                                class="w-full text-base!"
                            ><span class="truncate">{{ $link->title }}</span></flux:button>
                        @endforeach
                    </div>
                </div>
             @endif

            @include('partials.social-links')

            @unlesssubscribed($team)
                <div class="py-3">
                    @include('partials.powered-by')
                </div>
            @endsubscribed
        </div>
    </div>
</x-guest-layout>
