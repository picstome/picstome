<?php

use App\Jobs\PurgeUserAccount;
use App\Livewire\Forms\UserForm;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\StorageUsageService;
use App\Traits\InteractsWithTallStackModals;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use Interactions;
    use InteractsWithTallStackModals;
    use WithPagination;

    public $sortBy = 'created_at';

    public $sortDirection = 'desc';

    public $search = '';

    public string $filter = 'all';

    public string $referralFilter = '';

    public UserForm $userForm;

    #[Computed]
    public function users()
    {
        return $this->usersQuery()->paginate(25);
    }

    /**
     * Billable bytes for the teams on this page, in three grouped queries.
     *
     * The table shows a storage bar per row. Reading `storage_used_gb` per
     * user ran three aggregates each (36 queries for 18 rows), and the
     * per-team cache added one cache read per row on top.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function storageBytesByUserId(): array
    {
        $teams = $this->users->getCollection()
            ->map(fn (User $user) => $user->personalTeam())
            ->filter();

        return StorageUsageService::forTeams($teams->pluck('id')->all());
    }

    /**
     * Storage column values for one row, read from the batched totals. A null
     * return means the user has no personal team.
     *
     * @return array{used: string, limit: ?string, percent: ?float, unlimited: bool}|null
     */
    public function storageFor(User $user): ?array
    {
        $team = $user->personalTeam();

        if (! $team) {
            return null;
        }

        $bytes = (int) ($this->storageBytesByUserId[$team->id] ?? 0);
        $unlimited = (bool) $team->has_unlimited_storage;
        $limit = $team->storage_limit;

        return [
            'used' => number_format($bytes / 1073741824, 2).' GB',
            'limit' => $unlimited ? null : $team->storage_limit_gb,
            'percent' => $unlimited
                ? null
                : ($limit > 0 ? min(100, ($bytes / $limit) * 100) : 100),
            'unlimited' => $unlimited,
        ];
    }

    public function sort($column)
    {
        $this->sortDirection = ($this->sortBy === $column && $this->sortDirection === 'asc') ? 'desc' : 'asc';
        $this->sortBy = $column;
    }

    public function editUser(User $user)
    {
        $this->userForm->setUser($user);
        $this->openModal('edit-user');
    }

    public function saveUser()
    {
        $this->userForm->update();

        unset($this->users);

        $this->closeModal('edit-user');
    }

    /**
     * Empty accounts created more than seven days ago whose email is still
     * unverified. The acting admin and the configured admin emails are never
     * included, even if their own address has not been verified.
     */
    #[Computed]
    public function staleUnverifiedCount(): int
    {
        if ($this->filter !== 'unverified') {
            return 0;
        }

        return $this->staleUnverifiedUsersQuery()->count();
    }

    /**
     * Unverified accounts that also own galleries, contracts, invoices,
     * customers, payments, subscriptions or lifetime access. They are left
     * untouched so they can be reviewed individually.
     */
    #[Computed]
    public function staleUnverifiedWithDataCount(): int
    {
        if ($this->filter !== 'unverified') {
            return 0;
        }

        return $this->staleUnverifiedBaseQuery()
            ->whereHas('ownedTeams', $this->accountActivityScope())
            ->count();
    }

    public function openDeleteUnverifiedModal(): void
    {
        $this->ensureAdmin();

        $this->openModal('delete-unverified');
    }

    /**
     * Revoke access immediately and queue the heavy purge for each account.
     *
     * The purge deletes galleries, photos, contracts, invoices and Stripe
     * subscriptions in the background, so the request only walks the matching
     * ids and dispatches one job per user.
     */
    public function deleteUnverifiedUsers(AccountDeletionService $deletion): void
    {
        $this->ensureAdmin();

        $deleted = 0;

        $this->staleUnverifiedUsersQuery()
            ->select('id')
            ->chunkById(100, function ($users) use ($deletion, &$deleted): void {
                foreach ($users as $user) {
                    $deletion->revokeAccess($user);
                    PurgeUserAccount::dispatch($user->id);
                    $deleted++;
                }
            });

        unset($this->users, $this->staleUnverifiedCount);

        $this->closeModal('delete-unverified');

        if ($deleted === 0) {
            $this->toast()->warning(__('No accounts were deleted.'))->send();

            return;
        }

        $this->toast()->success(trans_choice(':count account is being deleted.|:count accounts are being deleted.', $deleted, [
            'count' => number_format($deleted),
        ]))->send();
    }

    /**
     * Close the flyout after the shared delete modal removed the account.
     *
     * Without this the flyout would keep rendering the deleted user's form and
     * the "Danger zone" button would point at a row that no longer exists.
     */
    #[On('user-deleted')]
    public function onUserDeleted(int $userId): void
    {
        if ($this->userForm->user?->id === $userId) {
            $this->closeModal('edit-user');

            $this->userForm->user = null;
        }

        unset($this->users);
    }

    public function exportCsv()
    {
        $filename = 'users-'.now()->format('Y-m-d_H-i-s').'.csv';
        $users = $this->usersQuery()->get();
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        return response()->streamDownload(function () use ($users) {
            // One grouped read for every row instead of three aggregates per
            // user while the stream is open.
            $teams = $users->map(fn (User $user) => $user->personalTeam())->filter();
            $bytes = StorageUsageService::forTeams($teams->pluck('id')->all());

            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID', 'Name', 'Email', 'Email Verified', 'Created At', 'Lifetime', 'Subscribed', 'Subscribed Since', 'Storage Used (GB)', 'Storage Limit (GB)',
            ]);
            foreach ($users as $user) {
                $team = $user->personalTeam();
                $subscribedSince = null;
                if ($team?->lifetime_at) {
                    $subscribedSince = $team->lifetime_at;
                } elseif ($team?->subscribed()) {
                    $subscribedSince = $team->subscriptions->first()?->created_at;
                }
                fputcsv($handle, [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->hasVerifiedEmail() ? 'Yes' : 'No',
                    $user->created_at,
                    $team?->lifetime_at ? 'Yes' : 'No',
                    $team?->subscribed() ? 'Yes' : 'No',
                    $subscribedSince,
                    $team ? number_format(((int) ($bytes[$team->id] ?? 0)) / 1073741824, 2) : null,
                    $team?->has_unlimited_storage ? 'Unlimited' : $team?->storage_limit_gb,
                ]);
            }
            fclose($handle);
        }, $filename, $headers);
    }

    /**
     * @return Builder<User>
     */
    private function staleUnverifiedBaseQuery(): Builder
    {
        $adminEmails = config('picstome.admin_emails', []);

        return User::query()
            ->whereNull('email_verified_at')
            ->where('created_at', '<', now()->subDays(7))
            ->whereKeyNot(auth()->id())
            ->when($adminEmails !== [], fn (Builder $query) => $query->whereNotIn('email', $adminEmails));
    }

    /**
     * Only accounts with nothing to lose: no galleries, business records,
     * billing history or lifetime access.
     *
     * @return Builder<User>
     */
    private function staleUnverifiedUsersQuery(): Builder
    {
        return $this->staleUnverifiedBaseQuery()
            ->whereDoesntHave('ownedTeams', $this->accountActivityScope());
    }

    /**
     * Matches a team that holds content, business records, billing history or
     * lifetime access.
     */
    private function accountActivityScope(): \Closure
    {
        return function (Builder $query): void {
            $query->where(function (Builder $query): void {
                $query->whereNotNull('lifetime_at')
                    ->orWhereHas('galleries')
                    ->orWhereHas('contracts')
                    ->orWhereHas('invoices')
                    ->orWhereHas('customers')
                    ->orWhereHas('payments')
                    ->orWhereHas('subscriptions');
            });
        };
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);
    }

    private function whereHasActiveSubscription(Builder $query, ?string $interval = null): Builder
    {
        return $query->where('personal_team', true)
            ->whereHas('subscriptions', fn (Builder $subscription) => $this->whereActiveSubscription($subscription, $interval));
    }

    private function whereDoesntHaveActiveSubscription(Builder $query, ?string $interval = null): Builder
    {
        return $query->where('personal_team', true)
            ->whereDoesntHave('subscriptions', fn (Builder $subscription) => $this->whereActiveSubscription($subscription, $interval));
    }

    private function whereActiveSubscription(Builder $query, ?string $interval = null): Builder
    {
        $query->where('stripe_status', 'active')
            ->where(function (Builder $query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            });

        if ($interval !== null) {
            $query->where('billing_interval', $interval);
        }

        return $query;
    }

    private function usersQuery()
    {
        $query = User::query()->with('ownedTeams.subscriptions');

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            });
        }

        if (! empty($this->referralFilter)) {
            $query->where('referral_code', 'like', '%'.$this->referralFilter.'%');
        }

        match ($this->filter) {
            'subscribed' => $query->whereHas('ownedTeams', fn (Builder $team) => $this->whereHasActiveSubscription($team)),
            'not_subscribed' => $query->whereHas('ownedTeams', fn (Builder $team) => $this->whereDoesntHaveActiveSubscription($team)),
            'monthly' => $query->whereHas('ownedTeams', fn (Builder $team) => $this->whereHasActiveSubscription($team, 'month')),
            'annual' => $query->whereHas('ownedTeams', fn (Builder $team) => $this->whereHasActiveSubscription($team, 'year')),
            'lifetime' => $query->whereHas('ownedTeams', function ($q) {
                $q->where('personal_team', true)
                    ->whereNotNull('lifetime_at');
            }),
            'verified' => $query->whereNotNull('email_verified_at'),
            'unverified' => $query->whereNull('email_verified_at'),
            default => null,
        };

        match ($this->sortBy) {
            'storage_used' => $query->addSelect([
                'storage_used' => DB::table('photos')
                    ->selectRaw('COALESCE(SUM(photos.size),0)')
                    ->join('galleries', 'photos.gallery_id', '=', 'galleries.id')
                    ->join('teams', 'galleries.team_id', '=', 'teams.id')
                    ->whereColumn('teams.user_id', 'users.id')
                    ->where('teams.personal_team', true),
            ])->orderBy('storage_used', $this->sortDirection),
            default => $query->orderBy($this->sortBy, $this->sortDirection),
        };

        return $query;
    }
}; ?>

<div>
    <x-ts-heading size="xl">{{ __('Users') }}</x-ts-heading>

    <div class="mt-4 flex flex-wrap items-end gap-4">
        <div class="flex-1">
            <x-ts-input
                wire:model.live="search"
                :label="__('Search users')"
                :placeholder="__('Search by name or email')"
                clearable
            />
        </div>
        <div class="w-full md:w-48">
            <x-ts-select wire:model.live="filter" label="{{ __('Filter') }}" placeholder="{{ __('All') }}">
                <x-ts-select-option value="all">{{ __('All') }}</x-ts-select-option>
                <x-ts-select-option value="subscribed">{{ __('Subscribed') }}</x-ts-select-option>
                <x-ts-select-option value="monthly">{{ __('Monthly subscription') }}</x-ts-select-option>
                <x-ts-select-option value="annual">{{ __('Annual subscription') }}</x-ts-select-option>
                <x-ts-select-option value="lifetime">{{ __('Lifetime') }}</x-ts-select-option>
                <x-ts-select-option value="not_subscribed">{{ __('Not Subscribed') }}</x-ts-select-option>
                <x-ts-select-option value="verified">{{ __('Verified email') }}</x-ts-select-option>
                <x-ts-select-option value="unverified">{{ __('Unverified email') }}</x-ts-select-option>
            </x-ts-select>
        </div>
        <div class="w-full md:w-48">
            <x-ts-input
                wire:model.live="referralFilter"
                :label="__('Referral Code')"
                :placeholder="__('Filter by referral code')"
                clearable
            />
        </div>
        <div>
            <x-ts-button wire:click="exportCsv" icon="arrow-down-tray" variant="primary" class="btn-ink">
                {{ __('Export CSV') }}
            </x-ts-button>
        </div>
        @if ($filter === 'unverified')
            <div>
                <x-ts-button
                    wire:click="openDeleteUnverifiedModal"
                    icon="trash"
                    variant="primary"
                    class="btn-coral"
                    data-test="delete-unverified-button"
                >
                    {{ __('Delete unverified') }}
                </x-ts-button>
            </div>
        @endif
    </div>

    <div
        x-data
        x-on:click="
            let el = $event.target
            while (el && el !== $el) {
                if (el.hasAttribute('wire:click')) {
                    document.getElementById('table')?.scrollIntoView({ behavior: 'smooth' })
                    break
                }
                el = el.parentElement
            }
        "
        class="mt-6"
    >
        <x-data-table id="table" class="mt-6">
            <x-data-table.columns>
                <x-data-table.column>{{ __('User') }}</x-data-table.column>
                <x-data-table.column>{{ __('Email status') }}</x-data-table.column>
                <x-data-table.column class="max-sm:hidden">{{ __('Referral Code') }}</x-data-table.column>
                <x-data-table.column
                    sortable
                    :sorted="$sortBy === 'created_at'"
                    :direction="$sortDirection"
                    wire:click="sort('created_at')"
                >
                    {{ __('Created At') }}
                </x-data-table.column>
                <x-data-table.column
                    sortable
                    :sorted="$sortBy === 'storage_used'"
                    :direction="$sortDirection"
                    wire:click="sort('storage_used')"
                >
                    {{ __('Storage') }}
                </x-data-table.column>
                <x-data-table.column class="max-sm:hidden">{{ __('Subscribed Since') }}</x-data-table.column>
                <x-data-table.column></x-data-table.column>
            </x-data-table.columns>
            <x-data-table.rows>
                @foreach ($this->users as $user)
                    <x-data-table.row :key="$user->id">
                        <x-data-table.cell>
                            <div class="flex items-center gap-2 sm:gap-4">
                                <x-ts-avatar :src="$user->avatar_url" circle size="lg" class="max-sm:size-8" />
                                <div class="flex flex-col">
                                    <x-ts-heading>
                                        {{ $user->name }}

                                        @if ($user->personalTeam()?->lifetime_at)
                                            <x-ts-badge color="lime" size="sm">
                                                {{ __('Lifetime') }}
                                            </x-ts-badge>
                                        @elseif ($user->personalTeam()?->subscribed())
                                            <x-ts-badge color="lime" size="sm">
                                                {{ __('Subscribed') }}
                                            </x-ts-badge>
                                        @endif
                                    </x-ts-heading>
                                    <x-ts-text class="max-sm:hidden">{{ $user->email }}</x-ts-text>
                                </div>
                            </div>
                        </x-data-table.cell>

                        <x-data-table.cell class="whitespace-nowrap">
                            @if ($user->hasVerifiedEmail())
                                <x-ts-badge color="lime" size="sm">{{ __('Verified') }}</x-ts-badge>
                            @else
                                <x-ts-badge color="amber" size="sm">{{ __('Unverified') }}</x-ts-badge>
                            @endif
                        </x-data-table.cell>

                        <x-data-table.cell class="whitespace-nowrap max-sm:hidden">
                            @if ($user->referral_code)
                                <x-ts-badge size="sm">{{ $user->referral_code }}</x-ts-badge>
                            @endif
                        </x-data-table.cell>

                        <x-data-table.cell class="whitespace-nowrap">
                            {{ $user->created_at?->format('M j, Y') }}
                        </x-data-table.cell>

                        <x-data-table.cell class="whitespace-nowrap">
                            @php
                                $storage = $this->storageFor($user);
                            @endphp

                            @if ($storage && $storage['unlimited'])
                                <div class="text-xs tabular-nums">
                                    {{ $storage['used'] }} / {{ __('Unlimited') }}
                                </div>
                            @elseif ($storage)
                                <div>
                                    <div class="text-xs tabular-nums">
                                        {{ $storage['used'] }} /
                                        {{ $storage['limit'] }}
                                    </div>
                                    <div
                                        class="bg-brand-sage/30 mt-1 h-1.5 w-full overflow-hidden rounded-full dark:bg-white/10"
                                    >
                                        <div
                                            class="{{ $storage['percent'] > 90 ? 'bg-brand-coral' : ($storage['percent'] > 75 ? 'bg-amber-400' : 'bg-brand-green dark:bg-brand-sage') }} h-full rounded-full transition-all duration-300"
                                            style="width: {{ min($storage['percent'], 100) }}%"
                                        ></div>
                                    </div>
                                </div>
                            @else
                                &mdash;
                            @endif
                        </x-data-table.cell>

                        <x-data-table.cell class="whitespace-nowrap max-sm:hidden">
                            @php
                                $team = $user->personalTeam();
                            @endphp

                            @if ($team?->lifetime_at)
                                {{ $team->lifetime_at->format('M j, Y') }}
                            @elseif ($team?->subscribed() && $subscription = $team->subscriptions->first())
                                {{ $subscription->created_at->format('M j, Y') }}
                            @else
                                &mdash;
                            @endif
                        </x-data-table.cell>

                        <x-data-table.cell align="end">
                            <form wire:submit="editUser({{ $user->id }})">
                                <x-ts-button
                                    type="submit"
                                    variant="ghost"
                                    size="sm"
                                    icon="ellipsis-horizontal"
                                    inset="top bottom"
                                    data-test="edit-user-{{ $user->id }}"
                                ></x-ts-button>
                            </form>
                        </x-data-table.cell>
                    </x-data-table.row>
                @endforeach
            </x-data-table.rows>
        </x-data-table>

        <x-ts-pagination :paginator="$this->users" />
    </div>

    <x-ts-modal name="delete-unverified" class="w-full max-w-lg">
        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <span
                    class="bg-brand-coral/10 text-brand-coral dark:bg-brand-coral/15 flex size-9 shrink-0 items-center justify-center rounded-full"
                >
                    <x-icon name="exclamation-triangle" class="size-5" />
                </span>

                <div>
                    <x-ts-heading size="lg">{{ __('Delete unverified accounts') }}</x-ts-heading>

                    <div class="mt-1" data-test="delete-unverified-count">
                        <x-ts-text>
                            @if ($this->staleUnverifiedCount > 0)
                                {{
                                    trans_choice(
                                        'This permanently deletes :count empty unverified account created more than 7 days ago. This cannot be undone.|This permanently deletes :count empty unverified accounts created more than 7 days ago. This cannot be undone.',
                                        $this->staleUnverifiedCount,
                                        ['count' => number_format($this->staleUnverifiedCount)],
                                    )
                                }}
                            @else
                                {{ __('There are no empty unverified accounts older than 7 days.') }}
                            @endif
                        </x-ts-text>
                    </div>

                    @if ($this->staleUnverifiedWithDataCount > 0)
                        <div
                            class="mt-3 rounded-lg border border-amber-300/40 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200"
                            data-test="delete-unverified-with-data"
                        >
                            {{
                                trans_choice(
                                    ':count unverified account has galleries or billing data and will not be deleted. Review it individually.|:count unverified accounts have galleries or billing data and will not be deleted. Review them individually.',
                                    $this->staleUnverifiedWithDataCount,
                                    ['count' => number_format($this->staleUnverifiedWithDataCount)],
                                )
                            }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <x-ts-button wire:click="closeModal('delete-unverified')" variant="ghost">
                    {{ __('Cancel') }}
                </x-ts-button>

                @if ($this->staleUnverifiedCount > 0)
                    <x-ts-button
                        wire:click="deleteUnverifiedUsers"
                        icon="trash"
                        variant="primary"
                        class="btn-coral"
                        data-test="confirm-delete-unverified"
                    >
                        {{ trans_choice('Delete :count account|Delete :count accounts', $this->staleUnverifiedCount, ['count' => number_format($this->staleUnverifiedCount)]) }}
                    </x-ts-button>
                @endif
            </div>
        </div>
    </x-ts-modal>

    <x-ts-modal name="edit-user" variant="flyout">
        @if ($userForm->user)
            <form wire:submit="saveUser" class="space-y-6">
                <div>
                    <x-ts-heading size="lg">{{ __('Update user') }}</x-ts-heading>
                    <x-ts-text class="mt-2">{{ __('Make changes to the user details.') }}</x-ts-text>
                </div>

                <x-ts-input :value="$userForm->user->name" :label="__('Name')" readonly />

                <x-ts-input :value="$userForm->user->email" :label="__('Email')" readonly />

                <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-white/10">
                    <x-ts-switch
                        wire:model="userForm.emailVerified"
                        :label="__('Email verified')"
                        :description="__('Mark the address as verified without sending the confirmation email.')"
                    />
                    <x-ts-error name="userForm.emailVerified" />
                </div>

                @if ($userForm->user->referral_code)
                    <x-ts-input :value="$userForm->user->referral_code" :label="__('Referral Code')" readonly />
                @endif

                <x-ts-switch
                    wire:model="userForm.lifetime"
                    :label="__('Lifetime access')"
                    :description="__('Grant this user lifetime access. Overrides subscription status.')"
                />

                <x-ts-field>
                    <x-ts-label>{{ __('Storage Limit') }}</x-ts-label>
                    <x-ts-input-group>
                        <x-ts-input wire:model="userForm.custom_storage_limit" type="number" step="0.01" />
                        <x-ts-input-group-suffix>GB</x-ts-input-group-suffix>
                    </x-ts-input-group>
                    <x-ts-description>{{ __('Set a custom storage limit for this user. Leave empty for unlimited.') }}</x-ts-description>
                </x-ts-field>

                <x-ts-field>
                    <x-ts-label>{{ __('Contracts per month') }}</x-ts-label>
                    <x-ts-input-group>
                        <x-ts-input wire:model="userForm.monthly_contract_limit" type="number" step="1" />
                        <x-ts-input-group-suffix>/{{ __('month') }}</x-ts-input-group-suffix>
                    </x-ts-input-group>
                    <x-ts-description>{{ __('Set how many contracts this user can create per month. Leave empty for unlimited.') }}</x-ts-description>
                </x-ts-field>

                <x-ts-field>
                    <x-ts-label>{{ __('Invoices per month') }}</x-ts-label>
                    <x-ts-input-group>
                        <x-ts-input wire:model="userForm.monthly_invoice_limit" type="number" step="1" />
                        <x-ts-input-group-suffix>/{{ __('month') }}</x-ts-input-group-suffix>
                    </x-ts-input-group>
                    <x-ts-description>{{ __('Set how many invoices this user can issue per month. Leave empty for unlimited.') }}</x-ts-description>
                </x-ts-field>

                <x-ts-field>
                    <x-ts-label>{{ __('Payments per month') }}</x-ts-label>
                    <x-ts-input-group>
                        <x-ts-input wire:model="userForm.monthly_payment_limit" type="number" step="1" />
                        <x-ts-input-group-suffix>/{{ __('month') }}</x-ts-input-group-suffix>
                    </x-ts-input-group>
                    <x-ts-description>{{ __('Set how many payments this user can receive per month. Leave empty for unlimited.') }}</x-ts-description>
                </x-ts-field>

                <div class="flex">
                    <x-ts-spacer />
                    <x-ts-button type="submit" variant="primary">{{ __('Save changes') }}</x-ts-button>
                </div>
            </form>

            {{-- Admins delete an account without knowing its password, so the
                 component switches to the typed-email confirmation. The admin's
                 own account is excluded: self-deletion belongs to the account
                 owner's settings, not to the user administration screen. --}}
            @if (! $userForm->user->is(auth()->user()))
                <div class="mt-8 border-t border-brand-coral/25 pt-6" data-test="admin-danger-zone">
                    <x-ts-heading size="lg" class="text-brand-coral-deep dark:text-brand-coral">
                        {{ __('Danger zone') }}
                    </x-ts-heading>
                    <x-ts-text class="mt-1">
                        {{ __('Deleting this account removes its galleries, photos, contracts, invoices and customers. This cannot be undone.') }}
                    </x-ts-text>

                    <div class="mt-4">
                        <x-ts-modal-trigger name="delete-account">
                            <x-ts-button variant="primary" class="btn-coral" icon="trash">
                                {{ __('Delete account') }}
                            </x-ts-button>
                        </x-ts-modal-trigger>
                    </div>
                </div>
            @endif
        @endif
    </x-ts-modal>

    @if ($userForm->user && ! $userForm->user->is(auth()->user()))
        <livewire:delete-account-modal
            :user="$userForm->user"
            :key="'delete-user-'.$userForm->user->id"
        />
    @endif
</div>
