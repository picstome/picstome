<?php

use App\Models\User;
use App\Services\AttributionService;
use App\Services\HandleGenerationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public $name = '';

    public $email = '';

    public $password = '';

    public $password_confirmation = '';

    public $terms = false;

    public $referral_code = '';

    public string $redirect = '';

    public function mount()
    {
        $this->referral_code = request()->get('referral', '');
        $this->redirect = $this->safeRedirect(request()->get('redirect', ''));

        app(AttributionService::class)->remember(request());
    }

    private function safeRedirect(?string $redirect): string
    {
        if (! is_string($redirect) || $redirect === '') {
            return '';
        }

        return str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//')
            ? $redirect
            : '';
    }

    public function register()
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'terms' => 'accepted',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        if (! empty($this->referral_code)) {
            $validated['referral_code'] = strtoupper($this->referral_code);
        }

        $user = User::create($validated);

        app(AttributionService::class)->persist($user, request());

        if ($this->redirect !== '') {
            session()->put('verification.redirect', $this->redirect);
        }

        event(new Registered($user));

        $handleGenerator = new HandleGenerationService;

        $user->ownedTeams()->create([
            'name' => "{$user->name}'s Studio",
            'handle' => $handleGenerator->generateUniqueHandle($user->name),
            'personal_team' => true,
            'custom_storage_limit' => config('picstome.personal_team_storage_limit'),
            'monthly_contract_limit' => config('picstome.personal_team_monthly_contract_limit'),
            'monthly_invoice_limit' => config('picstome.personal_team_monthly_invoice_limit'),
            'monthly_payment_limit' => config('picstome.personal_team_monthly_payment_limit'),
        ]);

        Auth::login($user);

        $this->dispatch('analytics:event', name: 'sign_up', params: array_filter([
            'method' => 'email',
            'signup_channel' => $user->attr_source,
            'signup_campaign' => $user->attr_campaign,
        ]));

        return redirect()->route('verification.notice');
    }
}; ?>

<div class="flex min-h-full items-center">
    <form wire:submit="register" class="mx-auto w-80 max-w-80 space-y-6">
        <div class="mb-10 space-y-3">
            <div class="flex justify-center">
                <img src="/app-logo.png" class="h-26 dark:hidden" alt="Picstome" />
                <img src="/app-logo-dark.png" class="hidden h-26 dark:block" alt="Picstome" />
            </div>

            <x-ts-text variant="strong" size="lg" class="text-center">
                {{ __('Already have an account?') }}
                <x-ts-link :href="route('login', $this->redirect !== '' ? ['redirect' => $this->redirect] : [])" variant="subtle" wire:navigate>
                    {{ __('Log in') }}
                </x-ts-link>
            </x-ts-text>
        </div>

        <x-ts-input wire:model="name" :label="__('Name')" type="text" placeholder="Your name" />

        <x-ts-input wire:model="email" :label="__('Email')" type="email" placeholder="email@example.com" />

        <x-ts-input
            wire:model="password"
            type="password"
            :label="__('Password')"
            :placeholder="__('Your password')"
        />

        <x-ts-input
            wire:model="password_confirmation"
            type="password"
            :label="__('Confirm Password')"
            :placeholder="__('Confirm your password')"
        />

        <x-checkbox wire:model="terms">
            <x-slot:label>
                {{ __('I accept the terms and conditions') }}
                <x-ts-link
                    :href="app()->getLocale() === 'es' ? 'https://picstome.com/es/terminos-y-condiciones/' : 'https://picstome.com/terms-and-conditions/'"
                    target="_blank"
                    variant="subtle"
                    class="ml-1 inline-flex align-middle"
                    :aria-label="__('Read the terms and conditions')"
                >
                    <x-icon name="arrow-top-right-on-square" variant="micro" />
                </x-ts-link>
            </x-slot:label>
        </x-checkbox>

        <x-ts-button type="submit" variant="primary" class="w-full">
            {{ __('Register') }}
        </x-ts-button>
    </form>
</div>
