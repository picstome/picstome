<flux:navlist>
    <flux:navlist.item href="{{ route('branding.general') }}" wire:navigate :active="request()->routeIs('branding.general')">
        General
    </flux:navlist.item>
    <flux:navlist.item href="{{ route('branding.logos') }}" wire:navigate :active="request()->routeIs('branding.logos')">
        Logos
    </flux:navlist.item>
    <flux:navlist.item href="{{ route('branding.watermark') }}" wire:navigate :active="request()->routeIs('branding.watermark')">
        Watermark
    </flux:navlist.item>
    <flux:navlist.item href="{{ route('branding.styling') }}" wire:navigate :active="request()->routeIs('branding.styling')">
        Styling
    </flux:navlist.item>
</flux:navlist>
