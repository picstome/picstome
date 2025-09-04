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

            @if($team->bioLinks->isNotEmpty())
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Links</h2>
                    <div class="space-y-3">
                        @foreach($team->bioLinks as $link)
                            <a
                                href="{{ $link->url }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="block p-3 rounded-lg border border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600 transition-colors"
                            >
                                <div class="font-medium text-gray-900 dark:text-white">{{ $link->title }}</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">{{ $link->url }}</div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="text-sm text-gray-500 dark:text-gray-400">
                <p>Welcome to {{ $team->name }}'s profile!</p>
            </div>
        </div>
    </div>
</x-guest-layout>
