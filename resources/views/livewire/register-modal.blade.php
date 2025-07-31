<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Component;

new class extends Component
{
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
        ]);

        Auth::login($user);

        $this->redirectIntended(route('galleries', absolute: false), navigate: true);
    }
} ?>

<div>
    <flux:modal name="register" class="w-full sm:max-w-sm">
        <form wire:submit="register" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Register') }}</flux:heading>
                <flux:subheading>{{ __('Create a new account') }}</flux:subheading>
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
    </flux:modal>
</div>
