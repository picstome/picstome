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
                 <a href="{{ route('handle.show', ['handle' => $team->handle]) }}" class="block py-4">
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
                             wire:navigate.hover
                         >
                             View Portfolio
                         </flux:button>
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
                                rel="noopener noreferrer nofollow"
                                class="w-full text-base!"
                            ><span class="truncate">{{ $link->title }}</span></flux:button>
                        @endforeach
                    </div>
                </div>
             @endif

             @if($team->hasSocialLinks())
                 <div class="my-14">
                     <div class="flex flex-wrap justify-center gap-4">
                         @if($team->instagram_url)
                             <a href="{{ $team->instagram_url }}" target="_blank" rel="noopener noreferrer nofollow">
                                 <flux:avatar size="lg" circle src="https://s.magecdn.com/social/tc-instagram.svg" />
                             </a>
                         @endif

                         @if($team->youtube_url)
                             <a href="{{ $team->youtube_url }}" target="_blank" rel="noopener noreferrer nofollow">
                                 <flux:avatar size="lg" circle src="https://s.magecdn.com/social/tc-youtube.svg" />
                             </a>
                         @endif

                         @if($team->facebook_url)
                             <a href="{{ $team->facebook_url }}" target="_blank" rel="noopener noreferrer nofollow">
                                 <flux:avatar size="lg" circle src="https://s.magecdn.com/social/tc-facebook.svg" />
                             </a>
                         @endif

                         @if($team->x_url)
                             <a href="{{ $team->x_url }}" target="_blank" rel="noopener noreferrer nofollow">
                                 <flux:avatar size="lg" circle src="https://s.magecdn.com/social/tc-x.svg" />
                             </a>
                         @endif

                         @if($team->tiktok_url)
                             <a href="{{ $team->tiktok_url }}" target="_blank" rel="noopener noreferrer nofollow">
                                 <flux:avatar size="lg" circle src="https://s.magecdn.com/social/tc-tiktok.svg" />
                             </a>
                         @endif

                         @if($team->twitch_url)
                             <a href="{{ $team->twitch_url }}" target="_blank" rel="noopener noreferrer nofollow">
                                 <flux:avatar size="lg" circle>
                                     <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                         <path d="M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0L1.714 4.286v15.428h5.143V24l4.286-4.286H13.714L22.286 10.857V0H6zm14.571 10.857l-3.429 3.429H13.714l-3 3v-3H6.857V1.714H20.57v9.143z"/>
                                     </svg>
                                 </flux:avatar>
                             </a>
                         @endif

                         @if($team->website_url)
                             <a href="{{ $team->website_url }}" target="_blank" rel="noopener noreferrer nofollow">
                                 <flux:avatar size="lg" circle src="https://unavatar.io/{{ parse_url($team->website_url, PHP_URL_HOST) }}" />
                             </a>
                         @endif

                         @if($team->other_social_links)
                             <a href="{{ $team->other_social_links['url'] }}" target="_blank" rel="noopener noreferrer nofollow">
                                 <flux:avatar size="lg" circle src="https://unavatar.io/{{ parse_url($team->other_social_links['url'], PHP_URL_HOST) }}" />
                             </a>
                         @endif
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
