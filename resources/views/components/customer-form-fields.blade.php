@props(['prefix' => 'form'])

<div class="space-y-4">
    <flux:input wire:model="{{ $prefix }}.name" :label="__('Name')" type="text" />
    <flux:input wire:model="{{ $prefix }}.email" :label="__('Email')" type="email" :required="false" />
    <flux:input wire:model="{{ $prefix }}.phone" :label="__('Phone')" type="text" />
    <flux:input wire:model="{{ $prefix }}.birthdate" :label="__('Birthdate')" type="date" />
    <flux:textarea wire:model="{{ $prefix }}.notes" :label="__('Notes')" rows="3" />

    <flux:separator />

    <flux:heading size="sm">{{ __('Fiscal data') }}</flux:heading>
    <flux:subheading size="sm">
        {{ __('Needed to issue a complete invoice. The data is copied to the invoice when it is issued.') }}
    </flux:subheading>

    <flux:input wire:model="{{ $prefix }}.legal_name" :label="__('Legal name')" :required="false" />
    <flux:input wire:model="{{ $prefix }}.tax_id" :label="__('Tax ID')" :required="false" />
    <flux:input wire:model="{{ $prefix }}.address" :label="__('Address')" :required="false" />
    <flux:input wire:model="{{ $prefix }}.city" :label="__('City')" :required="false" />
    <flux:input wire:model="{{ $prefix }}.state" :label="__('State / Province')" :required="false" />
    <flux:input wire:model="{{ $prefix }}.postal_code" :label="__('Postal code')" :required="false" />
    <flux:input
        wire:model="{{ $prefix }}.country"
        :label="__('Country')"
        :description="__('Two letter country code, for example ES.')"
        :required="false"
        maxlength="2"
    />
</div>
