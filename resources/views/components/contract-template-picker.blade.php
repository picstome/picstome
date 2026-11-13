@props(['templates'])

@php
    $ownTemplates = $templates->reject(fn ($template) => $template->isPublic());
    $publicTemplates = $templates->filter(fn ($template) => $template->isPublic());
@endphp

<form
    x-data="{ template: '', publicTemplate: false }"
    x-on:submit.prevent="if (template) $wire.useTemplate(template)"
    class="space-y-6"
>
    <div>
        <x-ts-heading size="lg">{{ __('Select template') }}</x-ts-heading>
        <x-ts-subheading>{{ __('Choose a template to start from.') }}</x-ts-subheading>
    </div>

    <x-ts-select
        x-model="template"
        x-on:change="publicTemplate = $event.target.selectedOptions[0]?.dataset.public === '1'"
        :label="__('Template')"
        :placeholder="__('Select a template')"
    >
        @if ($ownTemplates->isNotEmpty())
            <optgroup label="{{ __('My templates') }}">
                @foreach ($ownTemplates as $ownTemplate)
                    <option value="{{ $ownTemplate->id }}">{{ $ownTemplate->title }}</option>
                @endforeach
            </optgroup>
        @endif

        @if ($publicTemplates->isNotEmpty())
            <optgroup label="{{ __('Picstome contract templates') }}">
                @foreach ($publicTemplates as $publicTemplate)
                    <option value="{{ $publicTemplate->id }}" data-public="1">{{ $publicTemplate->title }}</option>
                @endforeach
            </optgroup>
        @endif
    </x-ts-select>

    <x-ts-callout x-show="publicTemplate" x-cloak variant="warning" icon="exclamation-triangle">
        <x-ts-callout-heading>{{ __('Picstome contract template') }}</x-ts-callout-heading>
        <x-ts-callout-text>
            {{ __('This template is a starting point, not legal advice. We are not lawyers and are not responsible for the contract content. Every country has different laws, so please review and adapt it before use.') }}
        </x-ts-callout-text>
    </x-ts-callout>

    <div class="flex">
        <x-ts-spacer />

        <x-ts-button type="submit" variant="primary" data-template-picker-submit>{{ __('Use template') }}</x-ts-button>
    </div>
</form>
