<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\User;
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
}; ?>

<x-app-layout>
    @volt('pages.users')
        <div>
            <flux:heading size="xl">{{ __('Users') }}</flux:heading>

            <flux:table :paginate="$this->users" class="mt-6">
                <flux:table.columns>
                    <flux:table.column>User</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">Created At</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->users as $user)
                        <flux:table.row :key="$user->id">
                            <flux:table.cell class="flex items-center gap-3">
                                <flux:avatar size="xs" :src="$user->avatar_url" />
                                {{ $user->name }}
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">
                                {{ $user->created_at->format('M j, Y') }}
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">
                                {{ $user->personalTeam()->storage_limit
                                    ? $user->personalTeam()->storage_limit_gb
                                    : __('Unlimited') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endvolt
</x-app-layout>
