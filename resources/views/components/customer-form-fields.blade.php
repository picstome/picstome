@props(['prefix' => 'form'])

<div class="space-y-4">
    <x-ts-input wire:model="{{ $prefix }}.name" :label="__('Name')" type="text" />
    <x-ts-input wire:model="{{ $prefix }}.email" :label="__('Email')" type="email" :required="false" />
    <x-ts-input wire:model="{{ $prefix }}.phone" :label="__('Phone')" type="text" />
    <x-ts-input wire:model="{{ $prefix }}.birthdate" :label="__('Birthdate')" type="date" />
    <x-ts-textarea wire:model="{{ $prefix }}.notes" :label="__('Notes')" rows="3" />

    <x-ts-separator />

    <x-ts-heading size="sm">{{ __('Fiscal data') }}</x-ts-heading>
    <x-ts-subheading size="sm">
        {{ __('These details are copied to the invoices you issue.') }}
    </x-ts-subheading>

    <x-ts-input wire:model="{{ $prefix }}.legal_name" :label="__('Legal name')" :required="false" />
    <x-ts-input wire:model="{{ $prefix }}.tax_id" :label="__('Tax ID')" :required="false" />
    <x-ts-input wire:model="{{ $prefix }}.address" :label="__('Address')" :required="false" />
    <x-ts-input wire:model="{{ $prefix }}.city" :label="__('City')" :required="false" />
    <x-ts-input wire:model="{{ $prefix }}.state" :label="__('State / Province')" :required="false" />
    <x-ts-input wire:model="{{ $prefix }}.postal_code" :label="__('Postal code')" :required="false" />
    <x-ts-input
        wire:model="{{ $prefix }}.country"
        :label="__('Country')"
        :description="__('Two letter country code, for example ES.')"
        :required="false"
        maxlength="2"
    />
</div>
