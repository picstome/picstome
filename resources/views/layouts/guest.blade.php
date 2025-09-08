<!DOCTYPE html>
<html lang="en" class="antialiased">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>{{ $title ?? config('app.name') }}</title>

        @if (! empty($font))
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
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:main :container="! ($fullScreen ?? false)" @class(['p-0!' => ($fullScreen) ?? false])>
            {{ $slot }}
        </flux:main>

        @fluxScripts
    </body>
</html>
