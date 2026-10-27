@props([
    'name',
    'title',
    'message' => null,
    'confirmLabel' => null,
    'method' => null,
    'alpineAction' => null,
])

{{--
    Confirmation dialog used across the app.

    `method` receives a Livewire action (e.g. "delete" or "deleteComment(3)").
    Component tags are never wrapped in conditionals inside this template:
    Blade mispairs the compiled `endif` when a component is nested in a
    conditional within another component's slot.
--}}

<x-ts-modal :name="$name" class="w-full sm:max-w-md">
    <div class="space-y-6">
        <div class="flex items-start gap-3">
            <span
                class="bg-brand-coral/10 text-brand-coral dark:bg-brand-coral/15 dark:text-brand-coral flex size-9 shrink-0 items-center justify-center rounded-full"
            >
                <x-icon name="exclamation-triangle" class="size-5" />
            </span>

            <div>
                <x-ts-heading size="lg">{{ $title }}</x-ts-heading>

                @if ($message)
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $message }}</p>
                @endif
            </div>
        </div>

        <div class="flex gap-2">
            <x-ts-spacer />

            <x-ts-modal-close>
                <x-ts-button variant="outline" class="btn-outline-brand">{{ __('Cancel') }}</x-ts-button>
            </x-ts-modal-close>

            <x-ts-modal-close>
                <x-ts-button
                    variant="primary"
                    class="btn-coral"
                    wire:click="{{ $method }}"
                    x-on:click="{{ $alpineAction }}"
                >
                    {{ $confirmLabel ?? __('Delete') }}
                </x-ts-button>
            </x-ts-modal-close>
        </div>
    </div>
</x-ts-modal>
