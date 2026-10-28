<nav class="space-y-1">
    <x-app-nav-link quiet :href="route('branding.general')" :current="request()->routeIs('branding.general')">
        {{ __('General') }}
    </x-app-nav-link>
    <x-app-nav-link quiet :href="route('branding.logos')" :current="request()->routeIs('branding.logos')">
        {{ __('Logos') }}
    </x-app-nav-link>
    <x-app-nav-link quiet :href="route('branding.watermark')" :current="request()->routeIs('branding.watermark')">
        {{ __('Watermark') }}
    </x-app-nav-link>
    <x-app-nav-link quiet :href="route('branding.styling')" :current="request()->routeIs('branding.styling')">
        {{ __('Styling') }}
    </x-app-nav-link>
    <x-app-nav-link quiet :href="route('branding.payments')" :current="request()->routeIs('branding.payments')">
        {{ __('Payments') }}
    </x-app-nav-link>
</nav>
