<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Computed;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use Interactions;

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
        heading="{{ __('Connected apps') }}"
        subheading="{{ __('Manage the applications that can access your account.') }}"
    >
        <div class="space-y-3">
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
    </x-settings.layout>
</section>
