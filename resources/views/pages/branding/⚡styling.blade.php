<?php

use TallStackUi\Traits\Interactions;
use App\Livewire\Forms\StylingForm;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use Interactions;
    use WithFileUploads;

    public Team $team;

    public StylingForm $form;

    public function save()
    {
        $this->form->update();

        $this->team = $this->team->fresh();

        $this->toast()->success(__('Your changes have been saved.'))->send();
    }

    public function mount()
    {
        $this->team = Auth::user()->currentTeam;

        $this->form->setTeam($this->team);
    }
}; ?>

@php
    $previewFontFamily = $form->font ? "'{$form->font}', sans-serif" : null;
    $previewFontUrl = $form->font
        ? 'https://fonts.googleapis.com/css2?family='.str_replace(' ', '+', $form->font).'&display=swap'
        : null;
@endphp

<section class="mx-auto max-w-6xl">
    @if ($previewFontUrl)
        <link
            wire:key="preview-font-{{ md5($previewFontUrl) }}"
            rel="stylesheet"
            href="{{ $previewFontUrl }}"
        />
    @endif
    @include('partials.branding-header')

    <div class="flex items-start max-md:flex-col">
        <div class="mr-10 w-full pb-4 md:w-[220px]">
            @include('partials.branding-nav')
        </div>

        <x-ts-separator class="md:hidden" />

        <div class="flex-1 self-stretch max-md:pt-6">
            <x-ts-heading>{{ __('Styling') }}</x-ts-heading>
            <x-ts-subheading>{{ __('Customize visual style of your studio.') }}</x-ts-subheading>

            <div class="mt-5 w-full max-w-lg">
                <form wire:submit="save" class="space-y-6">
                    <x-ts-select wire:model.live="form.color" :label="__('Accent color')" class="flex-wrap">
                        <option value="">{{ __('Picstome (default)') }}</option>
                        <option value="zinc">{{ __('Zinc') }}</option>
                        <option value="red">{{ __('Red') }}</option>
                        <option value="orange">{{ __('Orange') }}</option>
                        <option value="amber">{{ __('Amber') }}</option>
                        <option value="yellow">{{ __('Yellow') }}</option>
                        <option value="lime">{{ __('Lime') }}</option>
                        <option value="green">{{ __('Green') }}</option>
                        <option value="emerald">{{ __('Emerald') }}</option>
                        <option value="teal">{{ __('Teal') }}</option>
                        <option value="cyan">{{ __('Cyan') }}</option>
                        <option value="sky">{{ __('Sky') }}</option>
                        <option value="blue">{{ __('Blue') }}</option>
                        <option value="indigo">{{ __('Indigo') }}</option>
                        <option value="violet">{{ __('Violet') }}</option>
                        <option value="purple">{{ __('Purple') }}</option>
                        <option value="fuchsia">{{ __('Fuchsia') }}</option>
                        <option value="pink">{{ __('Pink') }}</option>
                        <option value="rose">{{ __('Rose') }}</option>
                    </x-ts-select>

                    <div class="border-brand-green/10 dark:border-white/10 rounded-xl border bg-white p-4 dark:bg-white/5">
                        <x-ts-text class="text-brand-green-deep dark:text-brand-sage text-xs font-semibold tracking-wide uppercase">
                            {{ __('Preview') }}
                        </x-ts-text>

                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <span style="font-family: {{ $previewFontFamily ?? 'inherit' }}">
                                @if ($form->color)
                                    <x-ts-button
                                        variant="primary"
                                        :color="$form->color"
                                        :href="route('handle.show', ['handle' => $team->handle])"
                                        target="_blank"
                                        icon:trailing="arrow-top-right-on-square"
                                    >
                                        {{ __('View Profile') }}
                                    </x-ts-button>
                                @else
                                    <x-ts-button
                                        variant="primary"
                                        class="[--color-accent:var(--color-brand-green)] [--color-accent-content:var(--color-brand-green)] [--color-accent-foreground:white]"
                                        :href="route('handle.show', ['handle' => $team->handle])"
                                        target="_blank"
                                        icon:trailing="arrow-top-right-on-square"
                                    >
                                        {{ __('View Profile') }}
                                    </x-ts-button>
                                @endif
                            </span>

                            <x-ts-text class="text-brand-ink/70 dark:text-brand-sage/80 text-sm font-medium">
                                {{ __('Applies to your public pages. The Picstome dashboard keeps its own theme.') }}
                            </x-ts-text>
                        </div>
                    </div>

                    <x-ts-select wire:model.live="form.font" :label="__('Font')">
                        <option value="">System sans-serif</option>
                        <option value="Roboto Flex">Roboto</option>
                        <option value="Raleway">Raleway</option>
                        <option value="Montserrat">Montserrat</option>
                        <option value="Work Sans">Work Sans</option>
                        <option value="Source Sans 3">Source Sans</option>
                        <option value="Nunito Sans">Nunito Sans</option>
                        <option value="Source Serif 4">Source Serif</option>
                        <option value="Roboto Serif">Roboto Serif</option>
                        <option value="Playfair Display">Playfair Display</option>
                    </x-ts-select>

                    <x-ts-button type="submit" variant="primary">{{ __('Save') }}</x-ts-button>
                </form>
            </div>
        </div>
    </div>
</section>
