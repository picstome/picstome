<?php

use App\Livewire\Forms\BioLinkForm;
use App\Models\BioLink;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('bio-links');

middleware(['auth', 'verified']);

new class extends Component
{
    public BioLinkForm $addForm;
    public BioLinkForm $editForm;

    public ?BioLink $editingLink = null;

    public function addLink()
    {
        $this->addForm->store();
        $this->addForm->resetForm();
        $this->modal('add-link')->close();
    }

    public function updateLink(BioLink $link)
    {
        $this->authorize('update', $link);

        $this->editForm->update();

        $this->editForm->resetForm();
        $this->editingLink = null;
        $this->modal('edit-link')->close();
    }

    public function deleteLink(BioLink $link)
    {
        $this->authorize('delete', $link);

        $link->delete();
    }

    public function reorderLink(BioLink $link, int $newOrder)
    {
        $this->authorize('update', $link);

        $link->reorder($newOrder);
    }

    public function editLink(BioLink $link)
    {
        $this->authorize('view', $link);

        $this->editingLink = $link;
        $this->editForm->setBioLink($link);
        $this->modal('edit-link')->show();
    }

    public function cancelEdit()
    {
        $this->editForm->resetForm();
        $this->editingLink = null;
        $this->modal('edit-link')->close();
    }

    #[Computed]
    public function team()
    {
        return Auth::user()->currentTeam;
    }

    #[Computed]
    public function bioLinks()
    {
        return $this->team?->bioLinks()->orderBy('order')->get() ?? collect();
    }
}; ?>

<x-app-layout>
    @volt('pages.bio-links')
        <div class="max-w-3xl mx-auto" x-data="{
            handleReorder: (item, position) => {
                $wire.call('reorderLink', item, position);
            }
        }">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="max-sm:w-full sm:flex-1">
                    <flux:heading level="1" size="xl">{{ __('Bio Links') }}</flux:heading>
                    <flux:text class="mt-2">
                        {{ __('Manage your bio links for your public profile.') }}
                        <flux:link :href="route('handle.show', ['handle' => $this->team->handle])" target="_blank">
                            {{ __('View your public profile') }}
                        </flux:link>.
                    </flux:text>
                </div>
             </div>

             <div class="mt-8">
                 <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="w-1/2">{{ __('Title') }}</flux:table.column>
                        <flux:table.column class="w-1/2">{{ __('URL') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                     </flux:table.columns>
                    <flux:table.rows x-sort="handleReorder">
                        @foreach ($this->bioLinks as $link)
                            <flux:table.row :key="$link->id" x-sort:item="{{ $link->id }}">
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        <flux:icon.bars-2 variant="micro" x-sort:handle class="cursor-move" />
                                        <flux:text variant="strong">{{ $link->title }}</flux:text>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{ $link->url }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:dropdown>
                                        <flux:button icon="ellipsis-vertical" variant="ghost" size="sm" />
                                        <flux:menu>
                                            <flux:menu.item wire:click="editLink({{ $link->id }})">
                                                Edit
                                            </flux:menu.item>
                                            <flux:menu.item
                                                variant="danger"
                                                wire:click="deleteLink({{ $link->id }})"
                                                wire:confirm="Are you sure you want to delete this bio link?"
                                            >
                                                Delete
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>
                            </flux:table.row>
                         @endforeach
                     </flux:table.rows>
                 </flux:table>
             </div>

             <div class="mt-8">
                 <flux:modal.trigger name="add-link">
                     <flux:button icon="plus" variant="filled">{{ __('Add Link') }}</flux:button>
                 </flux:modal.trigger>
             </div>

             <flux:modal name="add-link" class="md:w-96">
             <div class="space-y-6">
                 <div>
                     <flux:heading size="lg">{{ __('Add Bio Link') }}</flux:heading>
                     <flux:text class="mt-2">{{ __('Add a new link to your bio.') }}</flux:text>
                 </div>

                 <div class="space-y-4">
                     <flux:field>
                         <flux:label>{{ __('Title') }}</flux:label>
                         <flux:input wire:model="addForm.title" type="text" placeholder="e.g. Instagram" />
                         <flux:error name="addForm.title" />
                     </flux:field>

                     <flux:field>
                         <flux:label>{{ __('URL') }}</flux:label>
                         <flux:input wire:model="addForm.url" type="url" placeholder="https://instagram.com/username" />
                         <flux:error name="addForm.url" />
                     </flux:field>
                 </div>

                 <div class="flex gap-2 justify-end">
                     <flux:modal.close>
                         <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                     </flux:modal.close>
                     <flux:button wire:click="addLink" variant="primary">{{ __('Add Link') }}</flux:button>
                 </div>
             </div>
          </flux:modal>

          <flux:modal name="edit-link" class="md:w-96">
              <div class="space-y-6">
                  <div>
                      <flux:heading size="lg">{{ __('Edit Bio Link') }}</flux:heading>
                      <flux:text class="mt-2">{{ __('Update your bio link details.') }}</flux:text>
                  </div>

                  <div class="space-y-4">
                      <flux:field>
                          <flux:label>{{ __('Title') }}</flux:label>
                          <flux:input wire:model="editForm.title" type="text" placeholder="e.g. Instagram" />
                          <flux:error name="editForm.title" />
                      </flux:field>

                      <flux:field>
                          <flux:label>{{ __('URL') }}</flux:label>
                          <flux:input wire:model="editForm.url" type="url" placeholder="https://instagram.com/username" />
                          <flux:error name="editForm.url" />
                      </flux:field>
                  </div>

                  <div class="flex gap-2 justify-end">
                      <flux:modal.close>
                          <flux:button wire:click="cancelEdit" variant="ghost">{{ __('Cancel') }}</flux:button>
                      </flux:modal.close>
                      <flux:button wire:click="updateLink({{ $editingLink }})" variant="primary">{{ __('Update Link') }}</flux:button>
                  </div>
              </div>
          </flux:modal>

          @assets
             <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/sort@3.x.x/dist/cdn.min.js"></script>
         @endassets
     @endvolt
</x-app-layout>
