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

        @vite('resources/css/app.css')

        @if (! empty($color))
            <style>
                :root {
                    --color-accent: var(--color-{{ $color }}-500);
                    --color-accent-content: var(--color-{{ $color }}-600);
                }

                .dark {
                    --color-accent: var(--color-{{ $color }}-500);
                    --color-accent-content: var(--color-{{ $color }}-400);
                }
            </style>
        @endif

        @fluxAppearance

        {{ $head ?? '' }}

        @stack('head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:main :container="! ($fullScreen ?? false)" @class(['p-0!' => ($fullScreen) ?? false])>
            {{ $slot }}
        </flux:main>

        @fluxScripts
    </body>
</html>
