<?php

use App\Livewire\Forms\MoodboardForm;
use App\Models\Moodboard;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public MoodboardForm $form;

    public function save()
    {
        $this->authorize('create', Moodboard::class);

        tap($this->form->store(), function ($moodboard) {
            $this->redirect(route('moodboards.show', ['moodboard' => $moodboard]));
        });
    }

    #[Computed]
    public function team()
    {
        return Auth::user()?->currentTeam;
    }

    #[Computed]
    public function moodboards()
    {
        return $this->team->moodboards()
            ->latest()
            ->paginate(24);
    }
}; ?>

<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="max-sm:w-full sm:flex-1">
            <x-heading level="1" size="xl">{{ __('Moodboards') }}</x-heading>
            <x-subheading>{{ __('View, create, and manage your moodboards.') }}</x-subheading>
        </div>
        <flux:modal.trigger :name="auth()->check() ? 'create-moodboard' : 'login'">
            <flux:button variant="primary">{{ __('Create moodboard') }}</flux:button>
        </flux:modal.trigger>
    </div>

    @if ($this->moodboards?->isNotEmpty())
        <div class="mt-12">
            <div id="grid" class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                @foreach ($this->moodboards as $moodboard)
                    <flux:card
                        class="group relative overflow-hidden p-0! transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700"
                    >
                        <a
                            href="{{ route('moodboards.show', ['moodboard' => $moodboard]) }}"
                            class="block"
                            wire:navigate
                        >
                            <div
                                class="flex aspect-3/2 w-full items-center justify-center rounded-t-lg bg-zinc-200 dark:bg-zinc-700"
                            >
                                <flux:icon.photo class="size-12 text-zinc-400 dark:text-zinc-500" />
                            </div>

                            <div class="p-4">
                                <flux:heading size="lg" class="mb-2">
                                    {{ $moodboard->title }}
                                </flux:heading>

                                @if ($moodboard->description)
                                    <flux:text variant="subtle" size="sm" class="line-clamp-2 mb-2">
                                        {{ $moodboard->description }}
                                    </flux:text>
                                @endif

                                <div class="flex items-center justify-between">
                                    @if ($moodboard->created_at)
                                        <flux:text variant="subtle" size="sm">
                                            {{ $moodboard->created_at->format("M j, Y") }}
                                        </flux:text>
                                    @endif
                                </div>
                            </div>
                        </a>
                    </flux:card>
                @endforeach
            </div>

            <div
                x-data
                x-on:click="
                    let el = $event.target
                    while (el && el !== $el) {
                        if (el.hasAttribute('wire:click')) {
                            document.getElementById('grid')?.scrollIntoView({ behavior: 'smooth' })
                            break
                        }
                        el = el.parentElement
                    }
                "
                class="mt-6"
            >
                <flux:pagination :paginator="$this->moodboards" />
            </div>
        </div>
    @else
        <div class="mt-14 flex flex-1 flex-col items-center justify-center pb-32">
            <flux:icon.photo class="mb-6 size-12 text-zinc-500 dark:text-white/70" />
            <flux:heading size="lg" level="2">{{ __('No moodboards') }}</flux:heading>
            <flux:subheading class="mb-6 max-w-72 text-center">
                {{ __('We couldn\'t find any moodboards. Create one to get started.') }}
            </flux:subheading>
            <flux:modal.trigger :name="auth()->check() ? 'create-moodboard' : 'login'">
                <flux:button variant="primary">
                    {{ __('Create moodboard') }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    @endif

    <flux:modal name="create-moodboard" class="w-full sm:max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Create a new moodboard') }}</flux:heading>
                <flux:subheading>{{ __('Enter your moodboard details.') }}</flux:subheading>
            </div>

            <flux:input wire:model="form.title" :label="__('Title')" type="text" />

            <flux:textarea wire:model="form.description" :label="__('Description')" rows="3" />

            <div class="flex">
                <flux:spacer />

                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
