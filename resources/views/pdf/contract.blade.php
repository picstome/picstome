<x-guest-layout>
    <div class="mx-auto max-w-4xl">
        <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
            <div class="max-sm:w-full sm:flex-1">
                <div class="flex items-center gap-4">
                    <x-heading level="1" size="xl">{{ $contract->title }}</x-heading>
                </div>
                <x-subheading class="mt-2">{{ $contract->description }}</x-subheading>
            </div>
        </div>

        <div class="mt-12">
            <flux:heading level="2">{{ __('Details') }}</flux:heading>
            <flux:separator class="mt-4" />
            <x-description.list>
                <x-description.term>{{ __('Location') }}</x-description.term>
                <x-description.details>{{ $contract->location }}</x-description.details>

                <x-description.term>{{ __('Shooting date') }}</x-description.term>
                <x-description.details>{{ $contract->shooting_date }}</x-description.details>

                <x-description.term>{{ __('Contract terms') }}</x-description.term>
                <x-description.details>{{ $contract->markdown_body }}</x-description.details>
            </x-description.list>
        </div>

        <div class="mt-12">
            <div
                class="grid sm:grid-cols-3 gap-x-6 gap-y-6"
            >
                @foreach ($contract->signatures as $signature)
                    <div class="flex flex-col">
                        <img src="{{ $signature->signature_image_url }}" class="object-contain mb-6" />
                        <div>
                            <flux:heading class="text-center">{{ $signature->legal_name }}</flux:heading>
                            <flux:separator class="mt-4" />
                            <x-description.list>
                                <x-description.term>{{ __('Role') }}</x-description.term>
                                <x-description.details>{{ $signature->role }}</x-description.details>

                                <x-description.term>{{ __('Birthday') }}</x-description.term>
                                <x-description.details>{{ $signature->formattedBirthday }}</x-description.details>

                                <x-description.term>{{ __('Nationality') }}</x-description.term>
                                <x-description.details>{{ $signature->nationality }}</x-description.details>

                                <x-description.term>{{ __('Document number') }}</x-description.term>
                                <x-description.details>{{ $signature->document_number }}</x-description.details>

                                <x-description.term>{{ __('Email') }}</x-description.term>
                                <x-description.details>{{ $signature->email }}</x-description.details>
                            </x-description.list>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-guest-layout>
