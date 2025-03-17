<!DOCTYPE html>
<html lang="en" class="antialiased">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>{{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />

        @vite('resources/css/app.css')
        @fluxAppearance
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
                    <flux:brand
                        href="/"
                        :logo="auth()->user()->currentTeam->brand_logo_url ?? '/logo.png'"
                        :name="auth()->user()->currentTeam->name"
                        class="px-2"
                    />
                @else
                    <flux:brand href="/" logo="/logo.png" :name="__('Guest studio')" class="px-2" />
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
                </flux:navlist>

                <flux:spacer />

                <flux:navlist variant="outline">
                    <flux:navlist.group :heading="__('Studio')" class="mt-4">
                        <flux:navlist.item :href="route('branding')" icon="paint-brush">
                            {{ __('Branding') }}
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

        <flux:main :container="!$fullScreen">
            {{ $slot }}
        </flux:main>

        @guest
            <livewire:login-modal />
        @endguest

        @fluxScripts
    </body>
</html>
