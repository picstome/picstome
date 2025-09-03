<x-guest-layout>
    <div class="flex min-h-screen items-center justify-center">
        <div class="mx-auto max-w-md text-center">
            @if($team->brand_logo_url)
                <div class="mb-8">
                    <img src="{{ $team->brand_logo_url }}" alt="{{ $team->name }} logo" class="mx-auto h-24 w-auto" />
                </div>
            @endif

            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">
                {{ $team->name }}
            </h1>

            <p class="text-lg text-gray-600 dark:text-gray-400 mb-8">
                Profile handle: <span class="font-mono">{{ '@'.$team->handle }}</span>
            </p>

            <div class="text-sm text-gray-500 dark:text-gray-400">
                <p>Welcome to {{ $team->name }}'s profile!</p>
            </div>
        </div>
    </div>
</x-guest-layout>
