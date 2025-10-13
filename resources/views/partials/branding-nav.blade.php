<flux:navlist>
    <flux:navlist.item href="{{ route('branding.general') }}" wire:navigate :active="request()->routeIs('branding.general')">
        {{ __('General') }}
    </flux:navlist.item>
    <flux:navlist.item href="{{ route('branding.logos') }}" wire:navigate :active="request()->routeIs('branding.logos')">
        {{ __('Logos') }}
    </flux:navlist.item>
    <flux:navlist.item href="{{ route('branding.watermark') }}" wire:navigate :active="request()->routeIs('branding.watermark')">
        {{ __('Watermark') }}
    </flux:navlist.item>
    <flux:navlist.item href="{{ route('branding.styling') }}" wire:navigate :active="request()->routeIs('branding.styling')">
        {{ __('Styling') }}
    </flux:navlist.item>
    @if(auth()->user()->currentTeam->hasCompletedOnboarding())
        <flux:navlist.item href="{{ route('branding.pos') }}" wire:navigate :active="request()->routeIs('branding.pos')">
            {{ __('POS') }}
        </flux:navlist.item>
    @endif
</flux:navlist>
