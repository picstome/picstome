<?php

use App\Livewire\Forms\MoodboardForm;
use App\Models\Moodboard;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('moodboards.show');

middleware(['auth', 'verified', 'can:view,moodboard']);

new class extends Component
{
    public Moodboard $moodboard;

    public MoodboardForm $form;

    public bool $isEditing = false;

    public function mount(Moodboard $moodboard)
    {
        $this->moodboard = $moodboard;
        $this->form->setMoodboard($moodboard);
    }

    public function save()
    {
        $this->authorize('update', $this->moodboard);

        $this->form->update();

        $this->isEditing = false;

        Flux::toast(__('Moodboard updated successfully.'));
    }

    public function delete()
    {
        $this->authorize('delete', $this->moodboard);

        $this->moodboard->delete();

        return $this->redirect(route('moodboards'));
    }

    #[Computed]
    public function team()
    {
        return Auth::user()?->currentTeam;
    }
}; ?>

<x-app-layout>
    @volt('pages.moodboards.show')
        <div>
            <div class="mb-6">
                <flux:button
                    href="{{ route('moodboards') }}"
                    variant="ghost"
                    size="sm"
                    icon="arrow-left"
                    wire:navigate
                >
                    {{ __('Back to moodboards') }}
                </flux:button>
            </div>

            @if ($isEditing)
                <div class="mb-6 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="mb-4">
                        <flux:heading size="lg">{{ __('Edit moodboard') }}</flux:heading>
                        <flux:subheading>{{ __('Update your moodboard details.') }}</flux:subheading>
                    </div>

                    <form wire:submit="save" class="space-y-6">
                        <flux:input wire:model="form.title" :label="__('Title')" type="text" />

                        <flux:textarea wire:model="form.description" :label="__('Description')" rows="4" />

                        <div class="flex items-center gap-2">
                            <flux:button type="submit" variant="primary">
                                {{ __('Save changes') }}
                            </flux:button>

                            <flux:button wire:click="$set('isEditing', false)" variant="ghost">
                                {{ __('Cancel') }}
                            </flux:button>
                        </div>
                    </form>
                </div>
            @else
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <x-heading level="1" size="xl">
                            {{ $moodboard->title }}
                        </x-heading>
                        @if ($moodboard->description)
                            <flux:text variant="subtle" class="mt-2">
                                {{ $moodboard->description }}
                            </flux:text>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:button wire:click="$set('isEditing', true)" variant="ghost" icon="pencil">
                            {{ __('Edit') }}
                        </flux:button>

                        <flux:modal.trigger name="delete-moodboard">
                            <flux:button variant="ghost" icon="trash" color="danger">
                                {{ __('Delete') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-zinc-200 bg-zinc-50 p-12 text-center dark:border-zinc-800 dark:bg-zinc-800/50"
                >
                    <flux:icon.photo class="mx-auto mb-4 size-16 text-zinc-400 dark:text-zinc-500" />
                    <flux:heading size="lg" level="2" class="mb-2">
                        {{ __('Moodboard content coming soon') }}
                    </flux:heading>
                    <flux:text variant="subtle">
                        {{ __('This moodboard is currently empty. Content management will be added in the future.') }}
                    </flux:text>
                </div>
            @endif

            <flux:modal name="delete-moodboard" class="w-full sm:max-w-md">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Delete moodboard') }}</flux:heading>
                        <flux:subheading>
                            {{ __('Are you sure you want to delete this moodboard? This action cannot be undone.') }}
                        </flux:subheading>
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost">
                                {{ __('Cancel') }}
                            </flux:button>
                        </flux:modal.close>

                        <flux:button wire:click="delete" variant="danger">
                            {{ __('Delete') }}
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        </div>
    @endvolt
</x-app-layout>
