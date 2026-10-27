{{--
    Download tab content shared by the owner gallery view and the public
    shared gallery. It summarises the ZIP size before starting the download.
--}}

@props(['count', 'size', 'href'])

<div class="mx-auto max-w-2xl space-y-6 py-10">
    <x-ts-callout variant="warning" icon="exclamation-triangle">
        <x-ts-callout-heading>{{ __('Download gallery') }}</x-ts-callout-heading>
        <x-ts-callout-text>
            <div class="space-y-2">
                <p>
                    {{ __('This gallery has :count photos and weighs approximately :size.', [
                        'count' => $count,
                        'size' => $size,
                    ]) }}
                </p>
                <p>
                    {{ __("It probably won't download on a mobile device. We recommend using a computer with a stable connection.") }}
                </p>
                <p>{{ __('Remember you can also download photos individually.') }}</p>
            </div>
        </x-ts-callout-text>
    </x-ts-callout>
    <x-ts-button :href="$href" variant="primary" icon="arrow-down-tray" class="w-full sm:w-auto">
        {{ __('Download') }}
    </x-ts-button>
</div>
