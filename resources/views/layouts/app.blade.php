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

        <tallstackui:script />
        @vite('resources/css/app.css')
        @stack('head')
    </head>
    <body
        x-data="tallstackui_darkTheme({ name: 'dark-theme', default: 'system' })"
        x-bind:class="{ 'dark': darkTheme }"
        class="app-shell bg-brand-paper text-brand-ink dark:bg-brand-night dark:text-brand-cream min-h-screen"
    >
        @if ($fullScreen ?? false)
            <main class="min-h-screen">
                {{ $slot }}
            </main>
        @else
            <div x-data="{ sidebarOpen: false }" class="min-h-screen">
                <div
                    x-show="sidebarOpen"
                    x-cloak
                    x-on:click="sidebarOpen = false"
                    class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm lg:hidden"
                ></div>

                <aside
                    x-bind:class="sidebarOpen ? 'translate-x-0!' : ''"
                    class="border-brand-green/10 bg-brand-paper dark:bg-brand-night-deep fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r transition-transform duration-200 lg:translate-x-0 dark:border-white/10"
                >
                    <div class="flex items-center justify-between gap-2 px-4 pt-4 pb-6">
                        <a
                            href="{{ route('dashboard') }}"
                            wire:navigate
                            class="flex min-w-0 flex-1 justify-center"
                            aria-label="{{ __('Dashboard') }}"
                        >
                            @if (auth()->user()->currentTeam->brand_logo_icon_url)
                                <img
                                    src="{{ auth()->user()->currentTeam->brand_logo_icon_url }}"
                                    class="block h-16 rounded"
                                    alt="{{ auth()->user()->currentTeam->name }}"
                                />
                            @else
                                <img src="/app-logo.png" class="block h-16 rounded dark:hidden" alt="Picstome" />
                                <img src="/app-logo-dark.png" class="hidden h-16 rounded dark:block" alt="Picstome" />
                            @endif
                        </a>

                        <button
                            type="button"
                            x-on:click="sidebarOpen = false"
                            class="text-zinc-400 hover:text-zinc-700 lg:hidden dark:hover:text-white"
                            aria-label="{{ __('Close menu') }}"
                        >
                            <x-icon name="x-mark" class="size-5" />
                        </button>
                    </div>

                    <div class="px-4 pb-5">
                        <x-ts-modal-trigger name="search" shortcut="cmd.k">
                            <x-ts-input
                                as="button"
                                variant="filled"
                                :placeholder="__('Search...')"
                                icon="magnifying-glass"
                                kbd="⌘K"
                                class="hover:bg-brand-sage-soft! bg-white! dark:bg-white/10! dark:hover:bg-white/15!"
                            />
                        </x-ts-modal-trigger>

                        <x-ts-modal
                            name="search"
                            variant="bare"
                            class="my-[12vh] max-h-screen w-full max-w-[30rem] overflow-y-hidden px-2"
                        >
                            <livewire:search />
                        </x-ts-modal>
                    </div>

                    <nav data-sidebar-scroll class="min-h-0 flex-1 space-y-0.5 overflow-y-auto overscroll-contain px-4 pb-3" aria-label="Sidebar">
                        <x-app-nav-link :href="route('dashboard')" icon="home" :current="request()->routeIs('dashboard')">
                            {{ __('Dashboard') }}
                        </x-app-nav-link>

                        <x-app-nav-link
                            :href="route('public-profile')"
                            icon="at-symbol"
                            :current="request()->routeIs('public-profile')"
                        >
                            {{ __('Public Profile') }}
                        </x-app-nav-link>

                        <p class="px-3 py-2 text-sm leading-none font-medium text-zinc-400">
                            {{ __('Photos') }}
                        </p>

                        <x-app-nav-link
                            :href="route('galleries')"
                            icon="photo"
                            :current="request()->routeIs('galleries*')"
                        >
                            {{ __('Galleries') }}
                        </x-app-nav-link>

                        <x-app-nav-link
                            :href="route('photoshoots')"
                            icon="camera"
                            :current="request()->routeIs('photoshoots*')"
                        >
                            {{ __('Photoshoots') }}
                        </x-app-nav-link>

                        <x-app-nav-link
                            :href="route('portfolio')"
                            icon="briefcase"
                            :current="request()->routeIs('portfolio*')"
                        >
                            {{ __('Portfolio') }}
                        </x-app-nav-link>

                        <p class="mt-4 px-3 py-2 text-sm leading-none font-medium text-zinc-400">
                            {{ __('Manage') }}
                        </p>

                        <x-app-nav-link
                            :href="route('customers')"
                            icon="user-group"
                            :current="request()->routeIs('customers*') || request()->routeIs('clients*')"
                        >
                            {{ __('Customers') }}
                        </x-app-nav-link>

                        <x-app-nav-link
                            :href="route('contracts')"
                            icon="document-text"
                            :current="request()->routeIs('contracts*') || request()->routeIs('contract-templates*')"
                        >
                            {{ __('Contracts') }}
                        </x-app-nav-link>

                        <x-app-nav-link
                            :href="route('invoices')"
                            icon="document-plus"
                            :current="request()->routeIs('invoices*')"
                        >
                            {{ __('Invoices') }}
                        </x-app-nav-link>

                        <x-app-nav-link
                            :href="route('payments')"
                            icon="banknotes"
                            :current="request()->routeIs('payments*') || request()->routeIs('stripe.connect*')"
                        >
                            {{ __('Payments') }}
                        </x-app-nav-link>

                        <div x-data="{ open: {{ request()->routeIs('tools.*') ? 'true' : 'false' }} }" class="mt-4" data-sidebar-tools="{{ request()->routeIs('tools.*') ? 'open' : 'closed' }}">
                            <button
                                type="button"
                                x-on:click="open = ! open"
                                class="text-brand-ink hover:bg-brand-sage-soft/80 dark:text-brand-sage flex h-8 w-full items-center gap-3 rounded-lg px-3 text-sm font-semibold transition dark:hover:bg-white/7"
                            >
                                <span class="grid size-4 shrink-0 place-items-center">
                                    <x-icon name="chevron-down" class="size-3 transition" x-bind:class="{ 'rotate-180': open }" />
                                </span>
                                {{ __('Tools') }}
                            </button>

                            <div x-show="open" class="mt-1 space-y-0.5">
                                <x-app-nav-link
                                    :href="route('tools.calculator')"
                                    icon="calculator"
                                    :current="request()->routeIs('tools.calculator')"
                                >
                                    {{ __('Calculator') }}
                                </x-app-nav-link>

                                <x-app-nav-link
                                    :href="app()->getLocale() === 'es' ? 'https://picstome.com/es/descargar/' : 'https://picstome.com/download/'"
                                    icon="device-phone-mobile"
                                    target="_blank"
                                >
                                    {{ __('Picstome APP') }}
                                </x-app-nav-link>

                                <x-app-nav-link
                                    :href="app()->getLocale() === 'es' ? 'https://picstome.com/es/photo-stats/' : 'https://picstome.com/photostats/'"
                                    icon="chart-bar"
                                    target="_blank"
                                >
                                    {{ __('Photo Stats') }}
                                </x-app-nav-link>

                                <x-app-nav-link
                                    href="https://github.com/picstome/picstome-rater/releases/latest"
                                    icon="star"
                                    target="_blank"
                                >
                                    {{ __('Rater') }}
                                </x-app-nav-link>

                                <div class="relative flex items-center">
                                    <x-app-nav-link
                                        :href="route('tools.black-screen')"
                                        icon="computer-desktop"
                                        :current="request()->routeIs('tools.black-screen')"
                                        class="min-w-0 flex-1 pe-9!"
                                    >
                                        {{ __('Black Screen') }}
                                    </x-app-nav-link>

                                    <button
                                        type="button"
                                        x-data
                                        x-on:click="$dispatch('black-screen:start')"
                                        @class([
                                            'absolute end-1 top-1/2 -translate-y-1/2 rounded-md p-1.5 transition',
                                            'text-brand-cream/75 hover:bg-white/10 hover:text-brand-cream' => request()->routeIs('tools.black-screen'),
                                            'text-zinc-400 hover:bg-brand-sage-soft hover:text-brand-green dark:text-zinc-500 dark:hover:bg-white/10 dark:hover:text-white' => ! request()->routeIs('tools.black-screen'),
                                        ])
                                        title="{{ __('Turn screen black') }} (⌘B / Ctrl+B)"
                                        aria-label="{{ __('Turn screen black') }}"
                                        aria-keyshortcuts="Meta+B Control+B"
                                        data-black-screen-shortcut
                                    >
                                        <x-icon name="bolt" class="size-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        @if (auth()->user()?->is_admin)
                            <p class="mt-4 px-3 py-2 text-sm leading-none font-medium text-zinc-400">
                                {{ __('Admin') }}
                            </p>

                            <x-app-nav-link :href="route('users')" icon="user" :current="request()->routeIs('users')">
                                {{ __('Users') }}
                            </x-app-nav-link>

                            <x-app-nav-link
                                :href="route('admin.stats')"
                                icon="chart-bar"
                                :current="request()->routeIs('admin.stats')"
                            >
                                {{ __('Statistics') }}
                            </x-app-nav-link>

                            <x-app-nav-link
                                :href="route('admin.translations')"
                                icon="language"
                                :current="request()->routeIs('admin.translations')"
                            >
                                {{ __('Translations') }}
                            </x-app-nav-link>

                            <x-app-nav-link
                                :href="route('admin.emails')"
                                icon="envelope"
                                :current="request()->routeIs('admin.emails')"
                            >
                                {{ __('Emails') }}
                            </x-app-nav-link>
                        @endif
                    </nav>

                    <div class="mt-auto space-y-3 border-t border-brand-green/10 px-4 py-3 dark:border-white/10">
                        <livewire:storage-usage-indicator />

                        @unless (auth()->user()->currentTeam->subscribed())
                            <x-ts-button :href="route('subscribe')" variant="primary" size="sm" class="w-full">
                                {{ __('Subscribe') }}
                            </x-ts-button>
                        @endunless

                        <livewire:profile-dropdown />
                    </div>
                </aside>

                <div class="flex min-h-screen flex-col lg:pl-64">
                    <header
                        class="border-brand-green/10 bg-brand-paper/90 dark:bg-brand-night/90 flex items-center gap-3 border-b px-4 py-2 backdrop-blur lg:hidden dark:border-white/10"
                    >
                        <button
                            type="button"
                            x-on:click="sidebarOpen = true"
                            class="text-brand-ink dark:text-brand-cream"
                            aria-label="{{ __('Open menu') }}"
                        >
                            <x-icon name="bars-2" class="size-5" />
                        </button>

                        <x-ts-spacer />

                        <livewire:profile-dropdown :with-name="false" />
                    </header>

                    <main @class([
                        'min-w-0 flex-1',
                        'mx-auto w-full max-w-7xl p-6 lg:p-8' => ! ($isTool ?? false),
                        'p-0' => $isTool ?? false,
                    ])>
                        {{ $slot }}
                    </main>
                </div>
            </div>
        @endif

        @auth
            @include('partials.black-screen')
        @endauth

        @guest
            <livewire:login-modal />
            <livewire:register-modal />
        @endguest

        <x-toast />
    </body>
</html>
