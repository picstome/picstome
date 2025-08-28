<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

middleware(['auth']);

name('verification.notice');

new class extends Component {
    public function sendVerification()
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('galleries', absolute: false), navigate: true);

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

<x-guest-layout>
    @volt('pages.verify-email')
        <div class="flex min-h-full items-center">
            <div class="mx-auto w-80 max-w-80 space-y-6">
                <div class="mb-10 flex justify-center">
                    <img src="/app-logo.png" class="h-26 dark:hidden" alt="Picstome">
                    <img src="/app-logo-dark.png" class="h-26 hidden dark:block" alt="Picstome">
                </div>

                <flux:text class="text-center">
                    {{ __('Please verify your email address by clicking on the link we just emailed to you.') }}
                </flux:text>

                @if (session('status') == 'verification-link-sent')
                    <flux:text class="text-center font-medium !dark:text-green-400 !text-green-600">
                        {{ __('A new verification link has been sent to the email address you provided during registration.') }}
                    </flux:text>
                @endif

                <div class="flex flex-col items-center justify-between space-y-3">
                    <form wire:submit="sendVerification" class="w-full">
                        <flux:button type="submit" variant="primary" class="w-full">
                            {{ __('Resend verification email') }}
                        </flux:button>
                    </form>

                    <flux:link class="text-sm cursor-pointer" wire:click="logout">
                        {{ __('Log out') }}
                    </flux:link>
                </div>
            </div>
        </div>
    @endvolt
</x-guest-layout>
