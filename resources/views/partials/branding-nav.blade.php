<flux:navlist>
    <flux:navlist.item href="{{ route('branding.general') }}" :active="request()->routeIs('branding.general')">
        General
    </flux:navlist.item>
    <flux:navlist.item href="{{ route('branding.logos') }}" :active="request()->routeIs('branding.logos')">
        Logos
    </flux:navlist.item>
    <flux:navlist.item href="{{ route('branding.watermark') }}" :active="request()->routeIs('branding.watermark')">
        Watermark
    </flux:navlist.item>
    <flux:navlist.item href="{{ route('branding.styling') }}" :active="request()->routeIs('branding.styling')">
        Styling
    </flux:navlist.item>
</flux:navlist>
