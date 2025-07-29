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
        ]);

        Auth::login($user);

        $this->redirectIntended(route('galleries', absolute: false), navigate: true);
    }
}; ?>

<div>
    @volt('pages.register')
        <form wire:submit.prevent="register">
            <input type="text" wire:model="name" placeholder="Name">
            <input type="email" wire:model="email" placeholder="Email">
            <input type="password" wire:model="password" placeholder="Password">
            <input type="password" wire:model="password_confirmation" placeholder="Confirm Password">
            <button type="submit">Register</button>
        </form>
    @endvolt
</div>
</div>
