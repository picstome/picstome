<!DOCTYPE html>
<html lang="en" class="antialiased">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>{{ config('app.name') }}</title>

        <link rel="icon" href="/favicon.png">

        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />

        @vite('resources/css/app.css')
        @fluxAppearance
        @stack('head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        @unless ($fullScreen)
            <flux:sidebar
                sticky
                stashable
                class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900"
            >
                <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

                @auth
                    <div class="px-2">
                        @if (auth()->user()->currentTeam->brand_logo_icon_url)
                            <img
                                src="{{ auth()->user()->currentTeam->brand_logo_icon_url }}"
                                class="h-16 block rounded"
                                alt="{{ auth()->user()->currentTeam->name }}"
                            >
                        @else
                            <img src="/app-logo.png" class="h-16 block -mx-2 rounded dark:hidden" alt="Picstome">
                            <img src="/app-logo-dark.png" class="h-16 -mx-2 rounded hidden dark:block" alt="Picstome">
                        @endif
                    </div>
                @else
                    <flux:brand href="/" logo="/logo.png" class="px-2" />
                @endauth


                @auth
                    <flux:modal.trigger name="search" shortcut="cmd.k">
                        <flux:input
                            as="button"
                            variant="filled"
                            :placeholder="__('Search...')"
                            icon="magnifying-glass"
                            kbd="⌘K"
                        />
                    </flux:modal.trigger>

                    <flux:modal name="search" variant="bare" class="w-full max-w-[30rem] my-[12vh] max-h-screen overflow-y-hidden px-2">
                        <livewire:search />
                    </flux:modal>
                @endauth

                <flux:navlist variant="outline">
                    <flux:navlist.group :heading="__('Photos')">
                        <flux:navlist.item :href="route('galleries')" icon="photo">
                            {{ __('Galleries') }}
                        </flux:navlist.item>

                        <flux:navlist.item :href="route('photoshoots')" icon="camera">
                            {{ __('Photoshoots') }}
                        </flux:navlist.item>
                    </flux:navlist.group>

                    <flux:navlist.group :heading="__('Contracts')" class="mt-4">
                        <flux:navlist.item :href="route('contracts')" icon="document-text">
                            {{ __('Contracts') }}
                        </flux:navlist.item>
                        <flux:navlist.item :href="route('contract-templates')" icon="clipboard-document-list">
                            {{ __('Templates') }}
                        </flux:navlist.item>
                    </flux:navlist.group>

                    @if (auth()->user()?->is_admin)
                        <flux:navlist.group :heading="__('Admin')" class="mt-4">
                            <flux:navlist.item :href="route('users')" icon="user">
                                {{ __('Users') }}
                            </flux:navlist.item>
                        </flux:navlist.group>
                    @endif
                </flux:navlist>

                <flux:spacer />

                @auth
                    <livewire:storage-usage-indicator />

                    @unless(auth()->user()->currentTeam->subscribed())
                        <flux:button :href="route('subscribe')" size="sm" class="mx-2">{{ __('Subscribe') }}</flux:button>
                    @endunless
                @endauth

                <flux:navlist variant="outline">
                    <flux:navlist.group :heading="__('Studio')" class="mt-4">
                        <flux:navlist.item :href="route('branding')" icon="paint-brush">
                            {{ __('Branding') }}
                        </flux:navlist.item>

                        <flux:navlist.item :href="route('handle.show', ['handle' => auth()->user()->currentTeam->handle])" icon="eye" target="_blank">
                            {{ __('View Profile') }}
                        </flux:navlist.item>
                    </flux:navlist.group>
                </flux:navlist>

                <livewire:profile-dropdown />
            </flux:sidebar>

            <flux:header class="lg:hidden">
                <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

                <flux:spacer />

                <livewire:profile-dropdown :with-name="false" />
            </flux:header>
        @endunless

        <flux:main :container="!$fullScreen" @class(['p-0!' => $fullScreen])>
            {{ $slot }}
        </flux:main>

        @guest
            <livewire:login-modal />
            <livewire:register-modal />
        @endguest

        @fluxScripts

        @persist('toast')
            <flux:toast />
        @endpersist
    </body>
</html>
