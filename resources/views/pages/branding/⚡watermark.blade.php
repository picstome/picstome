<?php

use App\Livewire\Forms\WatermarkForm;
use App\Models\Team;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public Team $team;

    public WatermarkForm $form;

    public function save()
    {
        $this->form->update();

        $this->team = $this->team->fresh();

        Flux::toast(__('Your changes have been saved.'), variant: 'success');
    }

    public function mount()
    {
        $this->team = Auth::user()->currentTeam;

        $this->form->setTeam($this->team);
    }
}; ?>

<section class="mx-auto max-w-6xl">
    @include('partials.branding-header')

    <div class="flex items-start max-md:flex-col">
        <div class="mr-10 w-full pb-4 md:w-[220px]">
            @include('partials.branding-nav')
        </div>

        <flux:separator class="md:hidden" />

        <div class="flex-1 self-stretch max-md:pt-6">
            <flux:heading>{{ __('Watermark') }}</flux:heading>
            <flux:subheading>{{ __('Configure your watermark settings.') }}</flux:subheading>

            <div class="mt-5 w-full max-w-lg">
                <form wire:submit="save" class="space-y-6">
                    @if ($team->brand_watermark_url)
                        <div class="space-y-2">
                            <flux:label>{{ __('Current Watermark') }}</flux:label>
                            <div class="overflow-hidden overflow-x-scroll">
                                <img src="{{ $team->brand_watermark_url }}" class="max-w-none" />
                            </div>
                        </div>
                    @endif

                    <div>
                        <flux:input wire:model="form.watermark" :label="__('Watermark')" type="file" accept="image/*" :description:trailing="__('The recommended watermark height is 32px.')" />

                        <flux:text wire:loading wire:target="form.watermark" class="mt-2">{{ __('Uploading...') }}</flux:text>
                    </div>

                    <flux:radio.group wire:model="form.watermarkPosition" :label="__('Watermark position')">
                        <div class="flex gap-4 *:gap-x-2">
                            <flux:radio value="top" :label="__('Top')" />
                            <flux:radio value="bottom" :label="__('Bottom')" />
                            <flux:radio value="middle" :label="__('Middle')" />
                            <flux:radio value="repeated" :label="__('Repeated')" />
                        </div>
                    </flux:radio.group>

                    <flux:field>
                        <flux:label :badge="__('Optional')">{{ __('Watermark transparency') }}</flux:label>
                        <flux:input.group>
                            <flux:input wire:model="form.watermarkTransparency"
                                        type="number" min="0" max="100" class="max-w-20" />
                            <flux:input.group.suffix>%</flux:input.group.suffix>
                        </flux:input.group>
                    </flux:field>

                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </form>
            </div>
        </div>
    </div>
</section>
