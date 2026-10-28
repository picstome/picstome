<?php

use App\Traits\InteractsWithTallStackModals;
use App\Livewire\Forms\UserForm;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
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
        $this->closeModal('edit-user');
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
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID', 'Name', 'Email', 'Created At', 'Lifetime', 'Subscribed', 'Subscribed Since', 'Storage Used (GB)', 'Storage Limit (GB)',
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
                    $user->created_at,
                    $team?->lifetime_at ? 'Yes' : 'No',
                    $team?->subscribed() ? 'Yes' : 'No',
                    $subscribedSince,
                    $team?->storage_used_gb,
                    $team?->has_unlimited_storage ? 'Unlimited' : $team?->storage_limit_gb,
                ]);
            }
            fclose($handle);
        }, $filename, $headers);
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
            'subscribed' => $query->whereHas('ownedTeams', function ($q) {
                $q->where('personal_team', true)
                    ->whereHas('subscriptions', function ($q2) {
                        $q2->where('stripe_status', 'active')
                            ->where(function ($q3) {
                                $q3->whereNull('ends_at')
                                    ->orWhere('ends_at', '>', now());
                            });
                    });
            }),
            'not_subscribed' => $query->whereHas('ownedTeams', function ($q) {
                $q->where('personal_team', true)
                    ->whereDoesntHave('subscriptions', function ($q2) {
                        $q2->where('stripe_status', 'active')
                            ->where(function ($q3) {
                                $q3->whereNull('ends_at')
                                    ->orWhere('ends_at', '>', now());
                            });
                    });
            }),
            'lifetime' => $query->whereHas('ownedTeams', function ($q) {
                $q->where('personal_team', true)
                    ->whereNotNull('lifetime_at');
            }),
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
                <x-ts-select-option value="not_subscribed">{{ __('Not Subscribed') }}</x-ts-select-option>
                <x-ts-select-option value="lifetime">{{ __('Lifetime') }}</x-ts-select-option>
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
                                $team = $user->personalTeam();
                            @endphp

                            @if ($team?->has_unlimited_storage)
                                <div class="text-xs tabular-nums">
                                    {{ $team->storage_used_gb }} / {{ __('Unlimited') }}
                                </div>
                            @elseif ($team)
                                <div>
                                    <div class="text-xs tabular-nums">
                                        {{ $team->storage_used_gb }} /
                                        {{ $team->storage_limit_gb }}
                                    </div>
                                    <div
                                        class="bg-brand-sage/30 mt-1 h-1.5 w-full overflow-hidden rounded-full dark:bg-white/10"
                                    >
                                        <div
                                            class="{{ $team->storage_used_percent > 90 ? 'bg-brand-coral' : ($team->storage_used_percent > 75 ? 'bg-amber-400' : 'bg-brand-green dark:bg-brand-sage') }} h-full rounded-full transition-all duration-300"
                                            style="width: {{ min($team->storage_used_percent, 100) }}%"
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
                                ></x-ts-button>
                            </form>
                        </x-data-table.cell>
                    </x-data-table.row>
                @endforeach
            </x-data-table.rows>
        </x-data-table>

        <x-ts-pagination :paginator="$this->users" />
    </div>

    <x-ts-modal name="edit-user" variant="flyout">
        @if ($userForm->user)
            <form wire:submit="saveUser" class="space-y-6">
                <div>
                    <x-ts-heading size="lg">{{ __('Update user') }}</x-ts-heading>
                    <x-ts-text class="mt-2">{{ __('Make changes to the user details.') }}</x-ts-text>
                </div>

                <x-ts-input :value="$userForm->user->name" :label="__('Name')" readonly />

                <x-ts-input :value="$userForm->user->email" :label="__('Email')" readonly />

                @if ($userForm->user->referral_code)
                    <x-ts-input :value="$userForm->user->referral_code" :label="__('Referral Code')" readonly />
                @endif

                <x-ts-switch
                    wire:model="userForm.lifetime"
                    :label="__('Lifetime access')"
                    :description="__('Grant this user lifetime access. Overrides subscription status.')"
                />

                <x-ts-input-group
                    :label="__('Storage Limit')"
                    :description="__('Set a custom storage limit for this user. Leave empty for unlimited.')"
                >
                    <x-ts-input wire:model="userForm.custom_storage_limit" type="number" step="0.01" />
                    <x-ts-input-group-suffix>GB</x-ts-input-group-suffix>
                </x-ts-input-group>

                <x-ts-input-group
                    :label="__('Monthly contracts limit')"
                    :description="__('Set how many contracts this user can create per month. Leave empty for unlimited.')"
                >
                    <x-ts-input wire:model="userForm.monthly_contract_limit" type="number" step="1" />
                    <x-ts-input-group-suffix>/{{ __('month') }}</x-ts-input-group-suffix>
                </x-ts-input-group>

                <div class="flex">
                    <x-ts-spacer />
                    <x-ts-button type="submit" variant="primary">{{ __('Save changes') }}</x-ts-button>
                </div>
            </form>
        @endif
    </x-ts-modal>
</div>
