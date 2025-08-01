<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Livewire\Forms\UserForm;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithPagination;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('users');

middleware(['auth', 'verified', EnsureUserIsAdmin::class]);

new class extends Component {
    use WithPagination;

    public $sortBy = 'created_at';
    public $sortDirection = 'desc';
    public UserForm $userForm;

    public function sort($column) {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->tap(fn ($query) => $this->sortBy ? $query->orderBy($this->sortBy, $this->sortDirection) : $query)
            ->paginate(25);
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

            <flux:table :paginate="$this->users" class="mt-6">
                <flux:table.columns>
                    <flux:table.column>User</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">Created At</flux:table.column>
                    <flux:table.column>Storage</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->users as $user)
                        <flux:table.row :key="$user->id">
                            <flux:table.cell class="flex items-center gap-3">
                                <flux:avatar size="xs" :src="$user->avatar_url" />
                                {{ $user->name }}
                                @if ($user->personalTeam()->subscribed())
                                    <flux:badge color="lime" size="sm">Subscribed</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">
                                {{ $user->created_at->format('M j, Y') }}
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">
                                @if ($user->personalTeam()->has_unlimited_storage)
                                    {{  __('Unlimited') }}
                                @else
                                    <div>
                                        <div class="tabular-nums text-xs">{{  $user->personalTeam()->storage_limit_gb }}</div>
                                        <div class="w-full bg-zinc-200 rounded-full h-1.5 dark:bg-zinc-700 mt-1">
                                            <div
                                                class="h-1.5 rounded-full transition-all duration-300 {{ $user->personalTeam()->storage_used_percent > 90 ? 'bg-red-500' : ($user->personalTeam()->storage_used_percent > 75 ? 'bg-yellow-500' : 'bg-blue-500') }}"
                                                style="width: {{ min($user->personalTeam()->storage_used_percent, 100) }}%"
                                            ></div>
                                        </div>
                                    </div>
                                @endunless

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
                            <flux:heading size="lg">Update user</flux:heading>
                            <flux:text class="mt-2">Make changes to the user details.</flux:text>
                        </div>
                        <flux:input :value="$userForm->user->name" label="Name" readonly />
                        <flux:input.group :label="__('Storage Limit')" :description="__('Set a custom storage limit for this user. Leave empty for unlimited.')">
                            <flux:input wire:model="userForm.custom_storage_limit" type="number" step="0.01"  />
                            <flux:input.group.suffix>GB</flux:input.group.suffix>
                        </flux:input.group>
                        <div class="flex">
                            <flux:spacer />
                            <flux:button type="submit" variant="primary">Save changes</flux:button>
                        </div>
                    </form>
                @endif
            </flux:modal>
        </div>
    @endvolt
</x-app-layout>
