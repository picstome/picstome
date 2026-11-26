{{--
    Client payment fields shared by the payment settings and the booking
    settings. Both forms use the ManagesClientPaymentSettings trait, so there
    is one team-level configuration instead of two copies.
--}}
<div class="space-y-4">
    <div>
        <x-ts-heading size="sm">{{ __('Client details') }}</x-ts-heading>
        <x-ts-text class="mt-1 text-sm">
            {{ __('These details are shared by bookings and payment links. Change them here for both.') }}
        </x-ts-text>
    </div>

    <x-ts-switch
        wire:model.live="{{ $model }}.phone_required"
        :label="__('Require the phone number')"
        :description="__('The client must enter a phone number for bookings and Bizum payments.')"
    />

    <x-ts-switch
        wire:model.live="{{ $model }}.bizum_enabled"
        :label="__('Enable Bizum')"
        :description="__('The client sends the money to your phone and you confirm it manually.')"
    />

    @if (data_get($this, $model.'.bizum_enabled'))
        <div class="grid gap-5 sm:grid-cols-2">
            <x-ts-input
                wire:model="{{ $model }}.bizum_phone"
                :label="__('Bizum phone')"
                :description="__('The phone number your clients send the money to.')"
                type="tel"
            />

            <x-ts-textarea
                wire:model="{{ $model }}.bizum_instructions"
                :label="__('Payment instructions')"
                :description="__('Optional note shown after choosing Bizum.')"
                rows="3"
            />
        </div>
    @endif

    @error($model.'.bizum_phone')
        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
