<?php

use App\Livewire\Forms\PublicProfileForm;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('branding.public-profile');

middleware('auth');

new class extends Component
{
    public Team $team;

    public PublicProfileForm $form;

    public function save()
    {
        $this->form->update();

        $this->team = $this->team->fresh();

        $this->redirectRoute('branding.public-profile');
    }

    public function mount()
    {
        $this->team = Auth::user()->currentTeam;

        $this->form->setTeam($this->team);
    }
}; ?>

<x-app-layout>
    @volt('pages.branding.public-profile')
        <section class="mx-auto max-w-6xl">
            @include('partials.branding-header')

            <div class="flex items-start max-md:flex-col">
                <div class="mr-10 w-full pb-4 md:w-[220px]">
                    @include('partials.branding-nav')
                </div>

                <flux:separator class="md:hidden" />

                <div class="flex-1 self-stretch max-md:pt-6">
                    <flux:heading>{{ __('Public Profile') }}</flux:heading>
                    <flux:subheading>{{ __('Configure your public profile information.') }}</flux:subheading>

                    <div class="mt-5 w-full max-w-lg">
                        <form wire:submit="save" class="space-y-6">
                            <flux:field>
                                <flux:textarea wire:model="form.bio" :label="__('Bio')" :placeholder="__('Tell visitors about your studio...')" rows="4" />
                                <flux:description>
                                    {{ __('A short description that appears on your public profile. Maximum 1000 characters.') }}
                                </flux:description>
                            </flux:field>

                            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    @endvolt
</x-app-layout>