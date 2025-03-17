<?php

use App\Models\Gallery;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

use function Laravel\Folio\name;

name('shares.unlock');

new class extends Component
{
    public Gallery $gallery;

    public $password;

    public function unlock()
    {
        if (! Hash::check($this->password, $this->gallery->share_password)) {
            throw ValidationException::withMessages([
                'password' => trans('auth.failed'),
            ]);
        }

        session()->put('unlocked_gallery_ulid', $this->gallery->ulid);

        return $this->redirect("/shares/{$this->gallery->ulid}");
    }
}; ?>

<x-guest-layout>
    @volt('pages.shares.unlock')
        <div class="mx-auto mt-4 w-full md:w-96 lg:mt-8">
            <form wire:submit="unlock" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Protected gallery') }}</flux:heading>
                    <flux:subheading>{{ __('Enter the gallery password to unlock it.') }}</flux:subheading>
                </div>
                <flux:input wire:model="password" :label="__('Password')" type="text" />
                <div class="flex">
                    <flux:spacer />

                    <flux:button type="submit" variant="primary">Unlock</flux:button>
                </div>
            </form>
        </div>
    @endvolt
</x-guest-layout>
