<?php

use App\Jobs\PurgeUserAccount;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Traits\InteractsWithTallStackModals;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

/**
 * Irreversible account deletion, shared by the account owner and admins.
 *
 * Pass a user to delete someone else: the component then requires the admin
 * guard instead of the password, and refuses to delete the acting admin. With
 * no argument it deletes the authenticated account.
 *
 * Only the access revocation happens in this request. The purge itself runs in
 * `PurgeUserAccount`, so a large library does not keep the modal spinning.
 */
new class extends Component
{
    use Interactions;
    use InteractsWithTallStackModals;

    public ?User $user = null;

    public string $password = '';

    public string $confirmation = '';

    /**
     * The parameter is intentionally untyped: Livewire would otherwise resolve
     * a `User` type-hint from the container (an empty model) even when no
     * account was passed, which the authorization check below rejects.
     */
    public function mount($user = null): void
    {
        $user = $user instanceof User ? $user : null;

        if ($user && ! $user->is($this->actor())) {
            abort_unless($this->actor()->is_admin, 403);
        }

        $this->user = $user;
    }

    /**
     * Who is being deleted. Defaults to the authenticated user.
     */
    #[Computed]
    public function target(): User
    {
        return $this->user ?? $this->actor();
    }

    /**
     * An admin deleting someone else confirms with the target's email; the
     * owner confirms with their password plus their own email.
     */
    #[Computed]
    public function isAdminMode(): bool
    {
        return ! $this->target()->is($this->actor());
    }

    /**
     * What the user loses. Counts are cheap aggregates, not storage listings.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function inventory(): array
    {
        return app(AccountDeletionService::class)->inventory($this->target());
    }

    /**
     * Human readable storage size, reusing the dashboard formatter.
     */
    public function storageLabel(): string
    {
        return app(AccountDeletionService::class)->formattedStorage($this->inventory()['storage_bytes']);
    }

    public function delete(AccountDeletionService $deletion): void
    {
        $target = $this->target();

        $this->ensureIsNotRateLimited($target);

        if ($this->isAdminMode) {
            $this->deleteAsAdmin($target, $deletion);

            return;
        }

        $this->deleteOwnAccount($target, $deletion);
    }

    /**
     * The owner must prove they know the password and type their own email,
     * so a stolen session alone cannot destroy the account.
     */
    protected function deleteOwnAccount(User $target, AccountDeletionService $deletion): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
            'confirmation' => ['required', 'string'],
        ]);

        $this->ensureEmailMatches($target);

        $deletion->revokeAccess($target);

        PurgeUserAccount::dispatch($target->id);

        Auth::guard('web')->logout();

        Session::invalidate();
        Session::regenerateToken();

        session()->flash('status', __('Your account is being deleted. You have been signed out.'));

        $this->redirectRoute('login');
    }

    /**
     * Admins cannot know the password of the account they delete, so the
     * typed email is the confirmation. Self-deletion stays on the owner path.
     */
    protected function deleteAsAdmin(User $target, AccountDeletionService $deletion): void
    {
        abort_unless($this->actor()->is_admin, 403);

        $this->validate(['confirmation' => ['required', 'string']]);

        $this->ensureEmailMatches($target);

        $deletion->revokeAccess($target);

        PurgeUserAccount::dispatch($target->id);

        unset($this->inventory);

        $this->closeModal('delete-account');

        $this->toast()->success(__('The account is being deleted.'))->send();

        $this->dispatch('user-deleted', userId: $target->id);
    }

    protected function ensureEmailMatches(User $target): void
    {
        if (Str::lower(trim($this->confirmation)) !== Str::lower($target->email)) {
            throw ValidationException::withMessages([
                'confirmation' => __('The email address does not match this account.'),
            ]);
        }
    }

    /**
     * Password validation is the only barrier for a live session, so throttle
     * it: a stolen session could otherwise guess the password.
     */
    protected function ensureIsNotRateLimited(User $target): void
    {
        $key = 'delete-account:'.$this->actor()->id.':'.$target->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            // The admin modal has no password field; attach the error to the
            // confirmation input so the message is visible.
            $field = $target->is($this->actor()) ? 'password' : 'confirmation';

            throw ValidationException::withMessages([
                $field => __('Too many attempts. Try again in :seconds seconds.', ['seconds' => $seconds]),
            ]);
        }

        RateLimiter::hit($key, 300);
    }

    protected function actor(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}; ?>

<div>
    <x-ts-modal name="delete-account" class="w-full max-w-2xl overflow-y-auto">
    <div class="space-y-5">
        <div class="flex items-start gap-3">
            <span class="bg-brand-coral/10 text-brand-coral dark:bg-brand-coral/15 flex size-9 shrink-0 items-center justify-center rounded-full">
                <x-icon name="exclamation-triangle" class="size-5" />
            </span>

            <div>
                <x-ts-heading size="lg">{{ __('Delete account') }}</x-ts-heading>
                <x-ts-text class="mt-1">
                    @if ($this->isAdminMode)
                        {{ __('You are about to permanently delete the account of :name (:email).', ['name' => $this->target->name, 'email' => $this->target->email]) }}
                    @else
                        {{ __('This permanently deletes your account and everything in it. It cannot be undone.') }}
                    @endif
                </x-ts-text>
            </div>
        </div>

        {{-- Inventory: the point of the "big modal" is that nobody deletes an
             account without seeing what is inside it. --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-zinc-200 p-3 dark:border-white/10" data-test="delete-galleries">
                <div class="text-xs text-zinc-500">{{ __('Galleries') }}</div>
                <div class="text-lg font-semibold tabular-nums">{{ number_format($this->inventory['galleries']) }}</div>
            </div>
            <div class="rounded-lg border border-zinc-200 p-3 dark:border-white/10" data-test="delete-photos">
                <div class="text-xs text-zinc-500">{{ __('Photos and videos') }}</div>
                <div class="text-lg font-semibold tabular-nums">{{ number_format($this->inventory['photos']) }}</div>
            </div>
            <div class="rounded-lg border border-zinc-200 p-3 dark:border-white/10" data-test="delete-storage">
                <div class="text-xs text-zinc-500">{{ __('Storage in use') }}</div>
                <div class="text-lg font-semibold tabular-nums">{{ $this->storageLabel() }}</div>
            </div>
            <div class="rounded-lg border border-zinc-200 p-3 dark:border-white/10" data-test="delete-contracts">
                <div class="text-xs text-zinc-500">{{ __('Contracts') }}</div>
                <div class="text-lg font-semibold tabular-nums">{{ number_format($this->inventory['contracts']) }}</div>
            </div>
            <div class="rounded-lg border border-zinc-200 p-3 dark:border-white/10" data-test="delete-invoices">
                <div class="text-xs text-zinc-500">{{ __('Invoices') }}</div>
                <div class="text-lg font-semibold tabular-nums">{{ number_format($this->inventory['invoices']) }}</div>
            </div>
            <div class="rounded-lg border border-zinc-200 p-3 dark:border-white/10" data-test="delete-customers">
                <div class="text-xs text-zinc-500">{{ __('Customers') }}</div>
                <div class="text-lg font-semibold tabular-nums">{{ number_format($this->inventory['customers']) }}</div>
            </div>
        </div>

        <div class="space-y-2 text-sm text-zinc-600 dark:text-zinc-300">
            <p>{{ __('Download copies of anything you want to keep before continuing. Galleries, contracts and invoices are removed from the server and from storage.') }}</p>

            @if ($this->inventory['issued_invoices'] > 0)
                <p class="font-medium text-brand-coral-deep dark:text-brand-coral" data-test="delete-invoice-warning">
                    {{ __('You have :count issued invoice(s). They are your fiscal records: once deleted you will not be able to recover or re-download them.', ['count' => $this->inventory['issued_invoices']]) }}
                </p>
            @endif

            @if ($this->inventory['subscribed'])
                <p class="font-medium text-brand-coral-deep dark:text-brand-coral" data-test="delete-subscription-warning">
                    {{ __('Your subscription is cancelled immediately. The remaining paid time is not refunded.') }}

                    @if ($this->inventory['subscription_ends_at'])
                        {{ __('The current period would have ended on :date.', ['date' => $this->inventory['subscription_ends_at']]) }}
                    @endif
                </p>
            @elseif ($this->inventory['lifetime'])
                <p>{{ __('Your lifetime access is removed as well.') }}</p>
            @endif

            @if ($this->inventory['has_ftp'])
                <p>{{ __('The camera upload account and the credentials saved in your camera stop working.') }}</p>
            @endif

            @if ($this->inventory['app_tokens'] > 0)
                <p>{{ __('The :count connected app(s) lose access immediately.', ['count' => $this->inventory['app_tokens']]) }}</p>
            @endif
        </div>

        <x-ts-separator />

        @if ($this->isAdminMode)
            <x-ts-input
                wire:model="confirmation"
                :label="__('Type the account email to confirm')"
                :placeholder="$this->target->email"
                autocomplete="off"
            />
        @else
            <x-ts-input
                wire:model="password"
                :label="__('Your current password')"
                type="password"
                autocomplete="current-password"
            />

            <x-ts-input
                wire:model="confirmation"
                :label="__('Type your email to confirm')"
                :placeholder="$this->target->email"
                autocomplete="off"
            />
        @endif

        <div class="flex gap-2">
            <x-ts-spacer />

            <x-ts-modal-close>
                <x-ts-button variant="outline" class="btn-outline-brand">{{ __('Cancel') }}</x-ts-button>
            </x-ts-modal-close>

            <x-ts-button
                variant="primary"
                class="btn-coral"
                wire:click="delete"
                wire:loading.attr="disabled"
                icon="trash"
            >
                {{ __('Delete account') }}
            </x-ts-button>
        </div>
    </div>
</x-ts-modal>
</div>
