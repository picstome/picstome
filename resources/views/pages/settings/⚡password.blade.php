<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Computed;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use Interactions;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('sessions')
            ->where('user_id', Auth::id())
            ->where('id', '!=', session()->getId())
            ->delete();

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }

    /**
     * Personal access tokens issued to external clients. Only tokens with the
     * `sync` ability are shown; the plain text token is never stored.
     *
     * @return Collection<int, PersonalAccessToken>
     */
    #[Computed]
    public function tokens(): Collection
    {
        return Auth::user()
            ->tokens()
            ->latest()
            ->get()
            ->filter(fn (PersonalAccessToken $token): bool => in_array('sync', $token->abilities ?? [], true))
            ->values();
    }

    public function revoke(int $tokenId): void
    {
        $token = Auth::user()->tokens()->find($tokenId);

        if (! $token) {
            return;
        }

        $token->delete();

        unset($this->tokens);

        $this->toast()->success(__('Token revoked.'))->send();
    }

    /**
     * The token name is "{client_name}-{random6}"; hide the random suffix.
     */
    public function appName(PersonalAccessToken $token): string
    {
        return (string) str($token->name)->beforeLast('-');
    }

    public function isExpired(PersonalAccessToken $token): bool
    {
        return $token->expires_at !== null && $token->expires_at->isPast();
    }
}; ?>

<section class="mx-auto max-w-6xl">
    @include('partials.settings-heading')

    <x-settings.layout
        heading="{{ __('Update password') }}"
        subheading="{{ __('Ensure your account is using a long, random password to stay secure') }}"
    >
        <form wire:submit="updatePassword" class="mt-6 space-y-6">
            <x-ts-input
                wire:model="current_password"
                id="update_password_current_passwordpassword"
                label="{{ __('Current password') }}"
                type="password"
                name="current_password"
                required
                autocomplete="current-password"
            />
            <x-ts-input
                wire:model="password"
                id="update_password_password"
                label="{{ __('New password') }}"
                type="password"
                name="password"
                required
                autocomplete="new-password"
            />
            <x-ts-input
                wire:model="password_confirmation"
                id="update_password_password_confirmation"
                label="{{ __('Confirm Password') }}"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
            />

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <x-ts-button variant="primary" type="submit" class="w-full">
                        {{ __('Save') }}
                    </x-ts-button>
                </div>

                <x-action-message class="me-3" on="password-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>

        @if (auth()->user()->canSeeSyncFeature())
            <div class="mt-10 border-t border-zinc-200 pt-8 dark:border-white/10" data-test="connected-apps">
                <x-ts-heading>{{ __('Connected apps') }}</x-ts-heading>
                <x-ts-subheading>{{ __('Manage the applications that can access your account.') }}</x-ts-subheading>

                <div class="mt-5 space-y-3">
                    @forelse ($this->tokens as $token)
                        <div
                            wire:key="app-token-{{ $token->id }}"
                            class="rounded-xl border border-zinc-200 p-4 dark:border-white/10"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-zinc-900 dark:text-white">
                                        {{ $this->appName($token) }}
                                    </p>
                                    <p class="truncate text-xs text-zinc-500">{{ $token->name }}</p>
                                </div>

                                <x-ts-badge :color="$this->isExpired($token) ? 'red' : 'lime'" size="sm">
                                    {{ $this->isExpired($token) ? __('Expired') : __('Active') }}
                                </x-ts-badge>
                            </div>

                            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-zinc-500">
                                <div>
                                    <dt class="inline">{{ __('Created') }}:</dt>
                                    <dd class="inline">{{ $token->created_at?->format('M j, Y') ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="inline">{{ __('Last used') }}:</dt>
                                    <dd class="inline">{{ $token->last_used_at?->diffForHumans() ?? __('Never') }}</dd>
                                </div>
                                <div>
                                    <dt class="inline">{{ __('Expires') }}:</dt>
                                    <dd class="inline">{{ $token->expires_at?->format('M j, Y') ?? __('Never') }}</dd>
                                </div>
                                <div>
                                    <dt class="inline">{{ __('Scope') }}:</dt>
                                    <dd class="inline">{{ implode(', ', $token->abilities ?? []) ?: '—' }}</dd>
                                </div>
                            </dl>

                            <div class="mt-4 flex justify-end">
                                <x-ts-button
                                    wire:click="revoke({{ $token->id }})"
                                    wire:confirm="{{ __('Revoke this token? The application will need to be authorized again.') }}"
                                    variant="ghost"
                                    color="red"
                                    size="sm"
                                    icon="trash"
                                >
                                    {{ __('Revoke') }}
                                </x-ts-button>
                            </div>
                        </div>
                    @empty
                        <x-ts-callout variant="secondary" icon="information-circle">
                            <x-ts-callout-text>
                                {{ __('No connected apps yet. Authorize one from the desktop app.') }}
                            </x-ts-callout-text>
                        </x-ts-callout>
                    @endforelse
                </div>
            </div>
        @endif

        <div class="mt-10 border-t border-zinc-200 pt-8 dark:border-white/10" data-test="danger-zone">
            <x-ts-heading class="text-brand-coral-deep dark:text-brand-coral">
                {{ __('Danger zone') }}
            </x-ts-heading>
            <x-ts-text class="mt-1">
                {{ __('Deleting your account removes your galleries, photos, contracts, invoices and customers. This cannot be undone.') }}
            </x-ts-text>

            <div class="mt-4">
                <x-ts-modal-trigger name="delete-account">
                    <x-ts-button variant="primary" class="btn-coral" icon="trash">
                        {{ __('Delete account') }}
                    </x-ts-button>
                </x-ts-modal-trigger>
            </div>
        </div>
    </x-settings.layout>

    <livewire:delete-account-modal />
</section>
