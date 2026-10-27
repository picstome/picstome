<!DOCTYPE html>
<html lang="en" class="antialiased">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>{{ $title ?? config('app.name') }}</title>

        <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
        <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
        <link rel="shortcut icon" href="/favicon.ico" />
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
        <meta name="apple-mobile-web-app-title" content="Picstome" />
        <link rel="manifest" href="/site-b.webmanifest" />

        @if (! empty((string) ($font ?? '')))
            <link
                rel="stylesheet"
                href="https://fonts.googleapis.com/css2?family={{ str()->of($font)->replace(' ', '+') }}:ital,wght@0,400..900;1,400..900&display=swap"
            />
            <style>
                html,
                :host {
                    font-family: '{{ $font }}' !important;
                }
            </style>
        @else
            <link rel="preconnect" href="https://fonts.bunny.net" />
            <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />
        @endif

        <tallstackui:script />
        @vite('resources/css/app.css')

        @php
            $accentColor = trim((string) ($color ?? ''));
        @endphp

        @if (! empty($accentColor))
            <style>
                :root {
                    --color-primary-50: var(--color-{{ $accentColor }}-50);
                    --color-primary-100: var(--color-{{ $accentColor }}-100);
                    --color-primary-200: var(--color-{{ $accentColor }}-200);
                    --color-primary-300: var(--color-{{ $accentColor }}-300);
                    --color-primary-400: var(--color-{{ $accentColor }}-400);
                    --color-primary-500: var(--color-{{ $accentColor }}-500);
                    --color-primary-600: var(--color-{{ $accentColor }}-600);
                    --color-primary-700: var(--color-{{ $accentColor }}-700);
                    --color-primary-800: var(--color-{{ $accentColor }}-800);
                    --color-primary-900: var(--color-{{ $accentColor }}-900);
                    --color-primary-950: var(--color-{{ $accentColor }}-950);
                }
            </style>
        @else
            <style>
                :root {
                    --color-primary-500: var(--color-brand-green);
                    --color-primary-600: var(--color-brand-green-deep);
                }
            </style>
        @endif

        {{ $head ?? '' }}

        @stack('head')
    </head>
    <body
        x-data="tallstackui_darkTheme({ name: 'dark-theme', default: 'system' })"
        x-bind:class="{ 'dark': darkTheme }"
        @class([
            'min-h-screen',
            'bg-brand-paper dark:bg-brand-night' => empty($accentColor),
            'bg-white dark:bg-zinc-800' => ! empty($accentColor),
        ])
    >
        <main @class([
            'min-h-screen',
            'mx-auto w-full max-w-7xl px-4 py-6 lg:px-8' => ! ($fullScreen ?? false),
        ])>
            {{ $slot }}
        </main>

        <x-toast />
    </body>
</html>
