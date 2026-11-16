<?php

use App\Livewire\Forms\LoginForm;
use App\Models\User;
use App\Services\MagicLoginLink;
use App\Support\SafeRedirect;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public string $redirect = '';

    public bool $linkSent = false;

    public string $sentTo = '';

    public bool $showPassword = false;

    public function mount(): void
    {
        $this->redirect = $this->safeRedirect(request()->get('redirect', ''));

        if ($this->redirect === '') {
            $this->redirect = $this->intendedRedirect();
        }
    }

    /**
     * Relative path of the URL the auth middleware wanted before sending the
     * guest to login, keeping its query string intact.
     */
    private function intendedRedirect(): string
    {
        $intended = session()->pull('url.intended');

        if (! is_string($intended) || $intended === '') {
            return '';
        }

        $path = parse_url($intended, PHP_URL_PATH);
        $query = parse_url($intended, PHP_URL_QUERY);

        if (! is_string($path) || $path === '') {
            return '';
        }

        return SafeRedirect::sanitize($path.($query ? '?'.$query : '')) ?? '';
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

    /**
     * Email a single-use login link. The response never reveals whether the
     * address belongs to an account.
     */
    public function sendMagicLink(): void
    {
        $this->validate([
            'form.email' => ['required', 'string', 'email'],
        ]);

        $email = strtolower(trim($this->form->email));

        $this->ensureMagicLinkIsNotRateLimited($email);

        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            app(MagicLoginLink::class)->send($user, $this->redirect);
        }

        $this->sentTo = $email;
        $this->linkSent = true;
    }

    public function backToLogin(): void
    {
        $this->linkSent = false;
        $this->sentTo = '';
        $this->form->password = '';
    }

    public function login()
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        // A guest opened the verification email: complete it now and let the
        // verification route send them to the return URL carried by the link.
        $verificationUrl = session()->pull('verification.url');

        if (is_string($verificationUrl) && $verificationUrl !== '') {
            return redirect()->to($verificationUrl);
        }

        // Verification is mandatory: bring the return URL along so the
        // verification email can send them back to the page they wanted.
        if (! Auth::user()->hasVerifiedEmail()) {
            if ($this->redirect !== '') {
                session()->put('verification.redirect', $this->redirect);
            }

            return redirect()->route('verification.notice');
        }

        if ($this->redirect !== '') {
            return redirect()->to($this->redirect);
        }

        $this->redirectIntended(default: '/');
    }

    private function ensureMagicLinkIsNotRateLimited(string $email): void
    {
        $emailIpKey = 'magic-login:'.sha1($email.'|'.request()->ip());
        $ipKey = 'magic-login-ip:'.request()->ip();
        $emailKey = 'magic-login-email:'.sha1($email);

        if (RateLimiter::tooManyAttempts($emailIpKey, 5)
            || RateLimiter::tooManyAttempts($ipKey, 20)
            || RateLimiter::tooManyAttempts($emailKey, 3)) {
            $seconds = max(
                RateLimiter::availableIn($emailIpKey),
                RateLimiter::availableIn($ipKey),
                RateLimiter::availableIn($emailKey),
            );

            throw ValidationException::withMessages([
                'form.email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        RateLimiter::hit($emailIpKey, 900);
        RateLimiter::hit($ipKey, 900);
        RateLimiter::hit($emailKey, 900);
    }
}; ?>

<div class="flex min-h-full items-center">
    @if ($linkSent)
        <div class="mx-auto w-80 max-w-80 space-y-6 text-center">
            <div class="mb-10 flex justify-center">
                <img src="/app-logo.png" class="h-26 dark:hidden" alt="Picstome" />
                <img src="/app-logo-dark.png" class="h-26 hidden dark:block" alt="Picstome" />
            </div>

            <x-ts-heading size="lg">{{ __('Check your email') }}</x-ts-heading>

            <x-ts-text variant="strong" size="lg" class="font-medium">
                {{ __('We sent a login link to :email.', ['email' => $sentTo]) }}
            </x-ts-text>

            <x-ts-text variant="strong" size="sm">
                {{ __('If you do not see it, check your spam folder.') }}
            </x-ts-text>

            <x-ts-link wire:click="backToLogin" variant="subtle" class="font-medium">
                {{ __('Back to login') }}
            </x-ts-link>
        </div>
    @else
        <div class="mx-auto w-80 max-w-80 space-y-6">
            <div class="mb-10 flex justify-center">
                <img src="/app-logo.png" class="h-26 dark:hidden" alt="Picstome" />
                <img src="/app-logo-dark.png" class="h-26 hidden dark:block" alt="Picstome" />
            </div>

            @if(session('status'))
                <div class="rounded-lg border border-zinc-200 p-3 text-center text-sm dark:border-white/10" data-test="login-status">
                    {{ session('status') }}
                </div>
            @endif

            @if ($showPassword)
                <form wire:submit="login" class="space-y-6">
                    <x-ts-input wire:model="form.email" :label="__('Email')" type="email" placeholder="email@example.com" />

                    <div>
                        <x-ts-input
                            wire:model="form.password"
                            type="password"
                            :label="__('Password')"
                            :placeholder="__('Your password')"
                        />

                        <x-ts-text class="mt-2">
                            <x-ts-link variant="subtle" :href="route('password.request')" wire:navigate>
                                {{ __('Forgot your password?') }}
                            </x-ts-link>
                        </x-ts-text>
                    </div>

                    <x-ts-button type="submit" variant="primary" class="w-full">
                        {{ __('Log in') }}
                    </x-ts-button>

                    <x-ts-text class="text-center">
                        <x-ts-link wire:click="$set('showPassword', false)">
                            {{ __('Use a login link instead') }}
                        </x-ts-link>
                    </x-ts-text>
                </form>
            @else
                <form wire:submit="sendMagicLink" class="space-y-6">
                    <x-ts-input wire:model="form.email" :label="__('Email')" type="email" placeholder="email@example.com" />

                    <x-ts-button type="submit" variant="primary" class="w-full">
                        {{ __('Log in') }}
                    </x-ts-button>

                    <x-ts-text class="text-center">
                        <x-ts-link wire:click="$set('showPassword', true)">
                            {{ __('Log in with password') }}
                        </x-ts-link>
                    </x-ts-text>
                </form>
            @endif

            <x-ts-text class="text-center">
                {{ __('Don\'t have an account?') }}
                <x-ts-link :href="route('register', $this->redirect !== '' ? ['redirect' => $this->redirect] : [])" wire:navigate>
                    {{ __('Register') }}
                </x-ts-link>
            </x-ts-text>
        </div>
    @endif
</div>
