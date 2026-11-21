    <x-ts-modal name="edit" class="w-full sm:max-w-lg">
        <form wire:submit="update" class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Edit gallery') }}</x-ts-heading>
                <x-ts-subheading>{{ __('Enter your gallery details.') }}</x-ts-subheading>
            </div>

            <x-ts-input wire:model="form.name" :label="__('Gallery name')" type="text" />

            @if ($photoshoots)
                <x-ts-select
                    wire:model="form.photoshoot_id"
                    :label="__('Photoshoot')"
                    :placeholder="__('Choose photoshoot...')"
                >
                    <x-ts-select-option value="">{{ __('No photoshoot') }}</x-ts-select-option>
                    <hr />
                    @foreach ($photoshoots as $photoshoot)
                        <x-ts-select-option value="{{ $photoshoot->id }}">
                            {{ $photoshoot->name }}
                        </x-ts-select-option>
                    @endforeach
                </x-ts-select>
            @endif

            <x-ts-input
                wire:model="form.expirationDate"
                :label="__('Expiration date')"
                :badge="$this->team?->hasUnlimitedRetention() ? __('Optional') : null"
                type="date"
                :clearable="$this->team?->hasUnlimitedRetention()"
                :disabled="$gallery->is_public"
            />

            @unless ($this->team?->hasUnlimitedRetention())
                <x-ts-callout variant="secondary" icon="information-circle">
                    <x-ts-callout-text>
                        {{ __('This gallery will be deleted on :date. Your plan keeps galleries for one year from creation; subscribe to remove the expiration date.', ['date' => $gallery->created_at->copy()->addYear()->isoFormat('LL')]) }}
                    </x-ts-callout-text>
                </x-ts-callout>
            @endunless



            <x-ts-switch
                wire:model="form.keepOriginalSize"
                :label="__('Keep photos at their original size')"
                :description="__('Applies to photos uploaded from now on. Photos already uploaded keep their current size.')"
            />

            @if ($gallery->is_public)
                <x-ts-callout variant="secondary" icon="information-circle" class="mt-2">
                    <x-ts-callout-text>
                        {{ __('Public galleries do not expire. You cannot set an expiration date while the gallery is public.') }}
                    </x-ts-callout-text>
                </x-ts-callout>
            @endif

            <div class="flex">
                <x-ts-spacer />

                <x-ts-button type="submit" variant="primary">{{ __('Save') }}</x-ts-button>
            </div>
        </form>
    </x-ts-modal>

