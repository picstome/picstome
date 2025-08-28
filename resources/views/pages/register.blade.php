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
    public $terms = false;

    public function register()
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'terms' => 'accepted',
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
                <div class="mb-10 flex justify-center">
                    <img src="/app-logo.png" class="h-26 dark:hidden" alt="Picstome">
                    <img src="/app-logo-dark.png" class="h-26 hidden dark:block" alt="Picstome">
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

                <flux:field variant="inline">
                    <flux:checkbox wire:model="terms" />
                    <flux:label>
                        <div class="flex items-center gap-1">
                            @if(app()->getLocale() === 'es')
                                {{ __('I accept the terms and conditions') }}
                                <flux:link href="https://picstome.com/es/terminos-y-condiciones/" target="_blank" variant="subtle">
                                    <flux:icon.arrow-top-right-on-square variant="micro" />
                                </flux:link>
                            @else
                                {{ __('I accept the terms and conditions') }}
                                <flux:link href="https://picstome.com/terms-and-conditions/" target="_blank" variant="subtle">
                                    <flux:icon.arrow-top-right-on-square variant="micro" />
                                </flux:link>
                            @endif
                        </div>
                    </flux:label>
                    <flux:error name="terms" />
                </flux:field>

                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Register') }}
                </flux:button>
            </form>
        </div>
    @endvolt
</x-guest-layout>
