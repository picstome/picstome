<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Livewire\Forms\UserForm;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithPagination;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('users');

middleware(['auth', 'verified', EnsureUserIsAdmin::class]);

new class extends Component
{
    use WithPagination;

    public $sortBy = 'created_at';

    public $sortDirection = 'desc';

    public $search = '';

    public string $filter = 'all';

    public UserForm $userForm;

    public function sort($column)
    {
        $this->sortDirection = match (true) {
            $this->sortBy === $column && $this->sortDirection === 'asc' => 'desc',
            $this->sortBy === $column && $this->sortDirection === 'desc' => 'asc',
            default => 'asc',
        };

        $this->sortBy = $column;
    }

    #[Computed]
    public function users()
    {
        $query = User::query()->with('ownedTeams');

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            });
        }

        // Single filter logic
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
                    ->where('lifetime', true);
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

        return $query->paginate(25);
    }

    public function editUser(User $user)
    {
        $this->userForm->setUser($user);

        Flux::modal('edit-user')->show();
    }

    public function saveUser()
    {
        $this->userForm->update();

        Flux::modal('edit-user')->close();
    }
}; ?>

<x-app-layout>
    @volt('pages.users')
        <div>
            <flux:heading size="xl">{{ __('Users') }}</flux:heading>

            <div class="mt-4 flex gap-4 max-w-2xl">
                <div class="flex-1">
                    <flux:input
                        wire:model.live="search"
                        :label="__('Search users')"
                        :placeholder="__('Search by name or email')"
                        clearable
                    />
                </div>
                <div class="w-48">
<flux:select wire:model.live="filter" label="{{ __('Filter') }}" placeholder="{{ __('All') }}">
    <flux:select.option value="all">{{ __('All') }}</flux:select.option>
    <flux:select.option value="subscribed">{{ __('Subscribed') }}</flux:select.option>
    <flux:select.option value="not_subscribed">{{ __('Not Subscribed') }}</flux:select.option>
    <flux:select.option value="lifetime">{{ __('Lifetime') }}</flux:select.option>
</flux:select>
                </div>
            </div>

            <div x-data
                x-on:click="
                    let el = $event.target;
                    while (el && el !== $el) {
                        if (el.hasAttribute('wire:click')) {
                            document.getElementById('table')?.scrollIntoView({ behavior: 'smooth' });
                            break;
                        }
                        el = el.parentElement;
                    }"
                class="mt-6">
                <flux:table id="table" :paginate="$this->users" class="mt-6">
                <flux:table.columns>
                    <flux:table.column>{{ __('User') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">{{ __('Created At') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'storage_used'" :direction="$sortDirection" wire:click="sort('storage_used')">{{ __('Storage') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->users as $user)
                        <flux:table.row :key="$user->id">
                            <flux:table.cell>
                                <div class="flex items-center gap-2 sm:gap-4">
                                    <flux:avatar :src="$user->avatar_url" circle size="lg" class="max-sm:size-8" />
                                    <div class="flex flex-col">
                                        <flux:heading>
                                            {{ $user->name }}
                                            @if ($user->personalTeam()->lifetime)
                                                <flux:badge color="lime" size="sm">{{ __('Lifetime') }}</flux:badge>
                                            @elseif ($user->personalTeam()->subscribed())
                                                <flux:badge color="lime" size="sm">{{ __('Subscribed') }}</flux:badge>
                                            @endif
                                        </flux:heading>
                                        <flux:text class="max-sm:hidden">{{ $user->email }}</flux:text>
                                    </div>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="whitespace-nowrap">
                                {{ $user->created_at?->format('M j, Y') }}
                            </flux:table.cell>

                            <flux:table.cell class="whitespace-nowrap">
                                @if ($user->personalTeam()->has_unlimited_storage)
                                    <div class="tabular-nums text-xs">
                                        {{ $user->personalTeam()->storage_used_gb }} / {{ __('Unlimited') }}
                                    </div>
                                @else
                                    <div>
                                        <div class="tabular-nums text-xs">
                                            {{ $user->personalTeam()->storage_used_gb }} / {{ $user->personalTeam()->storage_limit_gb }}
                                        </div>
                                        <div class="w-full bg-zinc-200 rounded-full h-1.5 dark:bg-zinc-700 mt-1">
                                            <div
                                                class="h-1.5 rounded-full transition-all duration-300 {{ $user->personalTeam()->storage_used_percent > 90 ? 'bg-red-500' : ($user->personalTeam()->storage_used_percent > 75 ? 'bg-yellow-500' : 'bg-blue-500') }}"
                                                style="width: {{ min($user->personalTeam()->storage_used_percent, 100) }}%"
                                            ></div>
                                        </div>
                                    </div>
                                @endif
                            </flux:table.cell>


                            <flux:table.cell>
                                <form wire:submit="editUser({{ $user->id }})">
                                    <flux:button type="submit" variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>
                                </form>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <flux:modal name="edit-user" variant="flyout">
                @if ($userForm->user)
                    <form wire:submit="saveUser" class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('Update user') }}</flux:heading>
                            <flux:text class="mt-2">{{ __('Make changes to the user details.') }}</flux:text>
                        </div>

                        <flux:input :value="$userForm->user->name" :label="__('Name')" readonly />

                        <flux:input :value="$userForm->user->email" :label="__('Email')" readonly />

                        <flux:switch wire:model="userForm.lifetime" :label="__('Lifetime access')" :description="__('Grant this user lifetime access. Overrides subscription status.')" />

                        <flux:input.group :label="__('Storage Limit')" :description="__('Set a custom storage limit for this user. Leave empty for unlimited.')">
                            <flux:input wire:model="userForm.custom_storage_limit" type="number" step="0.01"  />
                            <flux:input.group.suffix>GB</flux:input.group.suffix>
                        </flux:input.group>

                        <flux:input.group :label="__('Monthly contracts limit')" :description="__('Set how many contracts this user can create per month. Leave empty for unlimited.')">
                            <flux:input wire:model="userForm.monthly_contract_limit" type="number" step="1"  />
                            <flux:input.group.suffix>/{{ __('month') }}</flux:input.group.suffix>
                        </flux:input.group>

                        <div class="flex">
                            <flux:spacer />
                            <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
                        </div>
                    </form>
                @endif
            </flux:modal>
        </div>
    @endvolt
</x-app-layout>
