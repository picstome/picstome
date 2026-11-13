<div class="flex items-start max-md:flex-col">
    <div class="mr-10 w-full pb-4 md:w-[220px]">
        <nav class="space-y-1">
            <x-app-nav-link quiet :href="route('settings.profile')" :current="request()->routeIs('settings.profile')">
                {{ __('Profile') }}
            </x-app-nav-link>
            <x-app-nav-link quiet :href="route('settings.password')" :current="request()->routeIs('settings.password')">
                {{ __('Password') }}
            </x-app-nav-link>
            <x-app-nav-link quiet :href="route('settings.appearance')" :current="request()->routeIs('settings.appearance')">
                {{ __('Appearance') }}
            </x-app-nav-link>
        </nav>
    </div>

    <x-ts-separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <x-ts-heading>{{ $heading ?? '' }}</x-ts-heading>
        <x-ts-subheading>{{ $subheading ?? '' }}</x-ts-subheading>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
