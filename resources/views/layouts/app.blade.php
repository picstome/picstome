<!DOCTYPE html>
<html lang="en" class="antialiased">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>{{ config('app.name') }}</title>

        <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
        <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
        <link rel="shortcut icon" href="/favicon.ico" />
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
        <meta name="apple-mobile-web-app-title" content="Picstome" />
        <link rel="manifest" href="/site-b.webmanifest" />

        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />

        @vite('resources/css/app.css')
        @fluxAppearance
        @stack('head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        @unless ($fullScreen ?? false)
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
                                class="block h-16 rounded"
                                alt="{{ auth()->user()->currentTeam->name }}"
                            />
                        @else
                            <img src="/app-logo.png" class="-mx-2 block h-16 rounded dark:hidden" alt="Picstome" />
                            <img src="/app-logo-dark.png" class="-mx-2 hidden h-16 rounded dark:block" alt="Picstome" />
                        @endif
                    </div>
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

                    <flux:modal
                        name="search"
                        variant="bare"
                        class="my-[12vh] max-h-screen w-full max-w-[30rem] overflow-y-hidden px-2"
                    >
                        <livewire:search />
                    </flux:modal>
                @endauth

                <flux:navlist>
                    <flux:navlist.item :href="route('dashboard')" icon="home" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:navlist.item>

                    <flux:navlist.item
                        :href="route('public-profile')"
                        icon="at-symbol"
                        wire:navigate
                        :current="request()->routeIs('public-profile')"
                    >
                        {{ __('Public Profile') }}
                    </flux:navlist.item>
                </flux:navlist>

                <flux:navlist variant="outline">
                    <flux:navlist.group :heading="__('Photos')">
                        <flux:navlist.item
                            :href="route('galleries')"
                            icon="photo"
                            wire:navigate
                            :current="request()->routeIs('galleries*')"
                        >
                            {{ __('Galleries') }}
                        </flux:navlist.item>

                        <flux:navlist.item
                            :href="route('photoshoots')"
                            icon="camera"
                            wire:navigate
                            :current="request()->routeIs('photoshoots*')"
                        >
                            {{ __('Photoshoots') }}
                        </flux:navlist.item>

                        <flux:navlist.item
                            :href="route('portfolio')"
                            icon="briefcase"
                            wire:navigate
                            :current="request()->routeIs('portfolio*')"
                        >
                            {{ __('Portfolio') }}
                        </flux:navlist.item>
                    </flux:navlist.group>

                    <flux:navlist.group :heading="__('Manage')" class="mt-4">
                        <flux:navlist.item
                            :href="route('customers')"
                            icon="user-group"
                            wire:navigate
                            :current="request()->routeIs('customers*') || request()->routeIs('clients*')"
                        >
                            {{ __('Customers') }}
                        </flux:navlist.item>

                        <flux:navlist.item
                            :href="route('contracts')"
                            icon="document-text"
                            wire:navigate
                            :current="request()->routeIs('contracts*') || request()->routeIs('contract-templates*')"
                        >
                            {{ __('Contracts') }}
                        </flux:navlist.item>

                        <flux:navlist.item
                            :href="route('payments')"
                            icon="banknotes"
                            wire:navigate
                            :current="request()->routeIs('payments*') || request()->routeIs('stripe.connect*')"
                        >
                            {{ __('Payments') }}
                        </flux:navlist.item>
                    </flux:navlist.group>

                    <flux:navlist.group
                        :heading="__('Tools')"
                        class="mt-4 [&>div.relative]:ps-0 [&>div.relative>div]:hidden"
                        expandable
                        :expanded="request()->routeIs('tools.*')"
                    >
                        <flux:navlist.item
                            :href="route('tools.invoice-generator')"
                            icon="document-plus"
                            wire:navigate
                        >
                            {{ __('Invoice Generator') }}
                        </flux:navlist.item>

                        <flux:navlist.item
                            :href="route('tools.calculator')"
                            icon="calculator"
                            wire:navigate
                        >
                            {{ __('Calculator') }}
                        </flux:navlist.item>

                        <flux:navlist.item
                            :href="app()->getLocale() === 'es' ? 'https://picstome.com/es/descargar/' : 'https://picstome.com/download/'"
                            icon="device-phone-mobile"
                            target="_blank"
                        >
                            {{ __('Picstome APP') }}
                        </flux:navlist.item>

                        <flux:navlist.item
                            :href="app()->getLocale() === 'es' ? 'https://picstome.com/es/photo-stats/' : 'https://picstome.com/photostats/'"
                            icon="chart-bar"
                            target="_blank"
                        >
                            {{ __('Photo Stats') }}
                        </flux:navlist.item>

                        <flux:navlist.item
                            href="https://github.com/picstome/picstome-rater/releases/latest"
                            icon="star"
                            target="_blank"
                        >
                            {{ __('Rater') }}
                        </flux:navlist.item>

                        <span class="flex items-center gap-1">
                            <flux:navlist.item
                                :href="route('tools.black-screen')"
                                icon="computer-desktop"
                                wire:navigate
                                :current="request()->routeIs('tools.black-screen')"
                                class="min-w-0 flex-1"
                            >
                                {{ __('Black Screen') }}
                            </flux:navlist.item>

                            <flux:button
                                x-data
                                x-on:click="$dispatch('black-screen:start')"
                                size="xs"
                                square
                                icon="computer-desktop"
                                icon:variant="solid"
                                tooltip:position="right"
                                :tooltip="__('Turn screen black')"
                                :aria-label="__('Turn screen black')"
                                class="border-black! bg-black! text-white! hover:bg-zinc-800! dark:border-zinc-600! dark:bg-black!"
                                data-black-screen-shortcut
                            />
                        </span>
                    </flux:navlist.group>

                    @if (auth()->user()?->is_admin)
                        <flux:navlist.group :heading="__('Admin')" class="mt-4">
                            <flux:navlist.item :href="route('users')" icon="user" wire:navigate>
                                {{ __('Users') }}
                            </flux:navlist.item>

                            <flux:navlist.item
                                :href="route('admin.stats')"
                                icon="chart-bar"
                                wire:navigate
                                :current="request()->routeIs('admin.stats')"
                            >
                                {{ __('Statistics') }}
                            </flux:navlist.item>
                        </flux:navlist.group>
                    @endif
                </flux:navlist>

                <flux:spacer />

                @auth
                    <livewire:storage-usage-indicator />

                    @unless (auth()->user()->currentTeam->subscribed())
                        <flux:button :href="route('subscribe')" size="sm" class="mx-2">
                            {{ __('Subscribe') }}
                        </flux:button>
                    @endunless
                @endauth

                <livewire:profile-dropdown />
            </flux:sidebar>

            <flux:header class="lg:hidden">
                <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

                <flux:spacer />

                <livewire:profile-dropdown :with-name="false" />
            </flux:header>
        @endunless

        <flux:main :container="!($fullScreen ?? false) && !($isTool ?? false)" @class(['p-0!' => ($fullScreen ?? false) || ($isTool ?? false)])>
            {{ $slot }}
        </flux:main>

        @auth
            @include('partials.black-screen')
        @endauth

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
