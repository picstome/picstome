@component('layouts.guest')
    <div class="flex min-h-[calc(100vh-3rem)] items-center justify-center px-4 py-10">
        <div class="w-full max-w-md space-y-6 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8 dark:border-white/10 dark:bg-zinc-800">
            <div class="text-center">
                <img src="/app-logo.png" class="mx-auto h-20 dark:hidden" alt="Picstome" />
                <img src="/app-logo-dark.png" class="mx-auto hidden h-20 dark:block" alt="Picstome" />

                <x-ts-heading size="xl" class="mt-6 block">{{ __('Authorize an application') }}</x-ts-heading>

                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('The application :client wants to access your Picstome account.', ['client' => $clientName]) }}
                </p>
            </div>

            <dl class="divide-y divide-zinc-200 rounded-lg bg-zinc-50 px-4 text-sm dark:divide-white/10 dark:bg-white/5">
                <div class="flex items-center justify-between gap-4 py-3">
                    <dt class="text-zinc-500">{{ __('Account') }}</dt>
                    <dd class="truncate font-medium text-zinc-900 dark:text-white">{{ $email }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 py-3">
                    <dt class="text-zinc-500">{{ __('Request') }}</dt>
                    <dd class="font-mono text-zinc-900 dark:text-white">{{ $noncePrefix }}</dd>
                </div>
            </dl>

            <div class="flex items-start gap-3 rounded-lg border border-zinc-200 p-4 dark:border-white/10">
                <x-icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-brand-green dark:text-brand-sage" />

                <div>
                    <p class="text-sm font-medium text-zinc-800 dark:text-white">{{ __('This grants:') }}</p>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __('View manifests, download, upload and delete photos in your galleries.') }}
                    </p>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <x-ts-button :href="route('external.authorize.cancel')" variant="outline" class="w-full sm:w-auto">
                    {{ __('Cancel') }}
                </x-ts-button>

                <form method="POST" action="{{ route('external.authorize.store') }}" class="w-full sm:w-auto">
                    @csrf
                    <input type="hidden" name="nonce" value="{{ $nonce }}" />
                    <input type="hidden" name="client_name" value="{{ $clientName }}" />

                    <x-ts-button type="submit" variant="primary" class="w-full">
                        {{ __('Authorize') }}
                    </x-ts-button>
                </form>
            </div>
        </div>
    </div>
@endcomponent
