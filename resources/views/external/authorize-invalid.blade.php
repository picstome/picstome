@component('layouts.guest')
    <div class="flex min-h-[calc(100vh-3rem)] items-center justify-center px-4 py-10">
        <div class="w-full max-w-md rounded-xl border border-zinc-200 bg-white p-6 text-center shadow-sm sm:p-8 dark:border-white/10 dark:bg-zinc-800">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-red-50 dark:bg-red-500/10">
                <x-icon name="exclamation-triangle" class="size-6 text-red-600 dark:text-red-400" />
            </div>

            <x-ts-heading size="xl" class="mt-5 block">{{ __('Invalid authorization request') }}</x-ts-heading>

            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('The request is missing a valid nonce or application name.') }}
            </p>

            @foreach ($errors->all() as $error)
                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
            @endforeach

            <x-ts-button :href="route('dashboard')" variant="primary" icon="home" class="mt-8 w-full">
                {{ __('Back to Picstome') }}
            </x-ts-button>
        </div>
    </div>
@endcomponent
