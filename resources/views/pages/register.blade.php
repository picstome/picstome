<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('register');

middleware('guest');

new class extends Component {
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';

    public function register()
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        $user->ownedTeams()->create([
            'name' => "{$user->name}'s Studio",
            'personal_team' => true,
            'custom_storage_limit' => config('picstome.personal_team_storage_limit'),
            'monthly_contract_limit' => config('picstome.personal_team_monthly_contract_limit'),
        ]);

        Auth::login($user);

        $this->redirectIntended(route('galleries', absolute: false), navigate: true);
    }
}; ?>

<x-guest-layout>
    @volt('pages.register')
        <div class="flex min-h-full items-center">
            <form wire:submit="register" class="mx-auto w-80 max-w-80 space-y-6">
                <div>
                    <div class="mb-12 flex items-center justify-center gap-3">
                        <x-logo-light class="size-5 dark:hidden" />
                        <span class="text-xl font-semibold text-zinc-800 dark:text-white">Picstome</span>
                    </div>
                </div>

                <flux:input wire:model="name" :label="__('Name')" type="text" placeholder="Your name" />

                <flux:input wire:model="email" :label="__('Email')" type="email" placeholder="email@example.com" />

                <flux:input
                    wire:model="password"
                    type="password"
                    :label="__('Password')"
                    :placeholder="__('Your password')"
                />

                <flux:input
                    wire:model="password_confirmation"
                    type="password"
                    :label="__('Confirm Password')"
                    :placeholder="__('Confirm your password')"
                />

                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Register') }}
                </flux:button>
            </form>
        </div>
    @endvolt
</x-guest-layout>
