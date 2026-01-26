<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public function sendVerification()
    {
        $key = 'send-verification:'.Auth::id().':'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);
            $this->addError('sendVerification', __('Too many attempts. Please try again in :seconds seconds.', ['seconds' => $seconds]));

            return;
        }

        RateLimiter::hit($key, 60);

        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    public function logout()
    {
        Auth::guard('web')->logout();

        Session::invalidate();
        Session::regenerateToken();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="flex min-h-full items-center">
    <div class="mx-auto w-80 max-w-80 space-y-6">
        <div class="mb-10 flex justify-center">
            <img src="/app-logo.png" class="h-26 dark:hidden" alt="Picstome" />
            <img src="/app-logo-dark.png" class="hidden h-26 dark:block" alt="Picstome" />
        </div>

        <flux:text class="text-center">
            {{ __('Please verify your email address by clicking on the link we just emailed to you.') }}
        </flux:text>

        @if (session('status') == 'verification-link-sent')
            <flux:text class="!dark:text-green-400 text-center font-medium !text-green-600">
                {{ __('A new verification link has been sent to the email address you provided during registration. Please check your inbox and spam folder.') }}
            </flux:text>
        @endif

        <div class="flex flex-col items-center justify-between space-y-3">
            <form wire:submit="sendVerification" class="w-full space-y-6">
                <flux:error name="sendVerification" />

                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Resend verification email') }}
                </flux:button>
            </form>

            <flux:link class="cursor-pointer text-sm" wire:click="logout">
                {{ __('Log out') }}
            </flux:link>
        </div>
    </div>
</div>
