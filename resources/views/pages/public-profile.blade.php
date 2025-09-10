<?php

use App\Livewire\Forms\BioLinkForm;
use App\Livewire\Forms\PublicProfileForm;
use App\Livewire\Forms\SocialLinksForm;
use App\Models\BioLink;
use App\Models\Team;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('public-profile');

middleware('auth');

new class extends Component
{
    public Team $team;

    public PublicProfileForm $form;
    public SocialLinksForm $socialLinksForm;
    public BioLinkForm $addForm;
    public BioLinkForm $editForm;

    public ?BioLink $editingLink = null;

    public function save()
    {
        $this->form->update();

        $this->team = $this->team->fresh();

        Flux::toast(__('Your changes have been saved.'), variant: 'success');
    }

    public function saveSocialLinks()
    {
        $this->socialLinksForm->update();

        $this->team = $this->team->fresh();

        $this->modal('social-links')->close();

        $this->dispatch('social-links-updated');

        Flux::toast(__('Your changes have been saved.'), variant: 'success');
    }

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

    #[Computed]
    public function bioLinks()
    {
        return $this->team->bioLinks()->orderBy('order')->get() ?? collect();
    }

    public function mount()
    {
        $this->team = Auth::user()->currentTeam;

        $this->form->setTeam($this->team);
        $this->socialLinksForm->setTeam($this->team);
    }
}; ?>

<x-app-layout>
    @volt('pages.public-profile')
        <section class="mx-auto max-w-6xl">
            @include('partials.branding-header')

            <div class="flex items-start max-md:flex-col">
                <flux:separator class="md:hidden" />

                <div class="flex-1 self-stretch max-md:pt-6">
                    <flux:heading>{{ __('Public Profile') }}</flux:heading>
                    <flux:subheading>{{ __('Configure your public profile information.') }}</flux:subheading>

                    <div class="mt-2">
                        <flux:button :href="route('handle.show', ['handle' => $team->handle])" target="_blank" icon:trailing="arrow-top-right-on-square" variant="filled" size="sm">
                            {{ __('View Profile') }}
                        </flux:button>
                    </div>

                    <div class="mt-5 space-y-12">
                        <!-- Handle Section -->
                        <div class="w-full max-w-lg">
                            <form wire:submit="save" class="space-y-4">
                                <flux:field>
                                    <flux:label>{{ __('Username') }}</flux:label>
                                    <flux:input wire:model="form.handle" :placeholder="__('e.g. mystudio')" />
                                    <flux:description>
                                        {{ __('This is your unique username that visitors will use to access your public profile at @') . $team->handle }}
                                    </flux:description>
                                    <flux:error name="form.handle" />
                                </flux:field>

                                <flux:field class="
                                    **:[trix-toolbar]:sticky **:[trix-toolbar]:top-0 **:[trix-toolbar]:z-10 **:[trix-toolbar]:bg-white
                                    **:[.trix-button-group--file-tools]:!hidden **:[.trix-button-group--history-tools]:!hidden"
                                >
                                    <flux:label>{{ __('Bio') }}</flux:label>

                                    <trix-editor
                                        input="trix"
                                        x-init="$nextTick(() => $el.editor.loadHTML($wire.form.bio))"
                                        x-on:trix-change="$wire.form.bio = $event.target.value"
                                        class="prose prose-sm mt-2"
                                    ></trix-editor>

                                    <input wire:model="form.bio" id="trix" type="text" class="hidden" />

                                    <flux:description>
                                        {{ __('A short description that appears on your public profile. Maximum 1000 characters.') }}
                                    </flux:description>

                                    <flux:error name="form.bio" />
                                </flux:field>

                                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                            </form>
                        </div>

                        <!-- Bio Links Section -->
                        <div class="w-full max-w-lg" x-data="{
                            handleReorder: (item, position) => {
                                $wire.call('reorderLink', item, position);
                            }
                        }">
                            <div class="space-y-4">
                                <div>
                                    <flux:heading size="sm">{{ __('Bio Links') }}</flux:heading>
                                    <flux:text class="mt-1">{{ __('Manage your bio links for your public profile.') }}</flux:text>
                                </div>

                                <div>
                                    @if($this->bioLinks->isNotEmpty())
                                        <flux:table>
                                            <flux:table.columns>
                                                <flux:table.column class="w-full sm:w-1/2">{{ __('Title') }}</flux:table.column>
                                                <flux:table.column class="w-1/2 hidden sm:table-cell">{{ __('URL') }}</flux:table.column>
                                                <flux:table.column></flux:table.column>
                                            </flux:table.columns>
                                            <flux:table.rows x-sort="handleReorder">
                                                @foreach ($this->bioLinks as $link)
                                                    <flux:table.row :key="$link->id" x-sort:item="{{ $link->id }}">
                                                        <flux:table.cell>
                                                            <div class="flex items-center gap-3">
                                                                <flux:button x-sort:handle variant="ghost" size="sm" inset="top bottom" class="cursor-move touch-manipulation" square>
                                                                    <flux:icon.bars-2 variant="micro" />
                                                                </flux:button>
                                                                <flux:text variant="strong">{{ $link->title }}</flux:text>
                                                            </div>
                                                        </flux:table.cell>
                                                        <flux:table.cell class="hidden sm:table-cell">
                                                            {{ $link->url }}
                                                        </flux:table.cell>
                                                        <flux:table.cell>
                                                            <flux:dropdown>
                                                                <flux:button icon="ellipsis-vertical" variant="ghost" size="sm" inset="top bottom" />
                                                                <flux:menu>
                                                                    <flux:menu.item wire:click="editLink({{ $link->id }})">
                                                                        {{ __('Edit') }}
                                                                    </flux:menu.item>
                                                                    <flux:menu.item
                                                                        variant="danger"
                                                                        wire:click="deleteLink({{ $link->id }})"
                                                                        wire:confirm="{{ __('Are you sure you want to delete this bio link?') }}"
                                                                    >
                                                                        {{ __('Delete') }}
                                                                    </flux:menu.item>
                                                                </flux:menu>
                                                            </flux:dropdown>
                                                        </flux:table.cell>
                                                    </flux:table.row>
                                                @endforeach
                                            </flux:table.rows>
                                        </flux:table>

                                        <div class="mt-2">
                                            <flux:modal.trigger name="add-link">
                                                <flux:button icon="plus" variant="filled">{{ __('Add Link') }}</flux:button>
                                            </flux:modal.trigger>
                                        </div>
                                    @else
                                        <flux:callout icon="link" variant="secondary">
                                            <flux:callout.heading>{{ __('Add Bio Links') }}</flux:callout.heading>
                                            <flux:callout.text>
                                                {{ __('Add links to your bio to help visitors discover your work and connect with you.') }}
                                            </flux:callout.text>
                                            <x-slot name="actions">
                                                <flux:modal.trigger name="add-link">
                                                    <flux:button>{{ __('Add Link') }}</flux:button>
                                                </flux:modal.trigger>
                                            </x-slot>
                                        </flux:callout>
                                    @endif
                                </div>
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
                                            <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                                        </flux:modal.close>
                                        <flux:button wire:click="updateLink({{ $editingLink }})" variant="primary">{{ __('Update Link') }}</flux:button>
                                    </div>
                                </div>
                            </flux:modal>
                        </div>

                        <!-- Social Links Section -->
                        <div class="w-full max-w-lg">
                            <div class="space-y-4">
                                <div>
                                    <flux:heading size="sm">{{ __('Social Links') }}</flux:heading>
                                    <flux:text class="mt-1">{{ __('Manage your social media profiles and website.') }}</flux:text>
                                </div>

                                 @if($team->hasSocialLinks())
                                     <flux:avatar.group>
                                         @if($team->instagram_url)
                                             <flux:tooltip content="{{ $team->instagram_url }}">
                                                 <flux:avatar circle src="https://s.magecdn.com/social/tc-instagram.svg" />
                                             </flux:tooltip>
                                         @endif

                                         @if($team->youtube_url)
                                             <flux:tooltip content="{{ $team->youtube_url }}">
                                                 <flux:avatar circle src="https://s.magecdn.com/social/tc-youtube.svg" />
                                             </flux:tooltip>
                                         @endif

                                         @if($team->facebook_url)
                                             <flux:tooltip content="{{ $team->facebook_url }}">
                                                 <flux:avatar circle src="https://s.magecdn.com/social/tc-facebook.svg" />
                                             </flux:tooltip>
                                         @endif

                                         @if($team->x_url)
                                             <flux:tooltip content="{{ $team->x_url }}">
                                                 <flux:avatar circle src="https://s.magecdn.com/social/tc-x.svg" />
                                             </flux:tooltip>
                                         @endif

                                         @if($team->tiktok_url)
                                             <flux:tooltip content="{{ $team->tiktok_url }}">
                                                 <flux:avatar circle src="https://s.magecdn.com/social/tc-tiktok.svg" />
                                             </flux:tooltip>
                                         @endif

                                         @if($team->twitch_url)
                                             <flux:tooltip content="{{ $team->twitch_url }}">
                                                 <flux:avatar circle>
                                                     <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                                         <path d="M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0L1.714 4.286v15.428h5.143V24l4.286-4.286H13.714L22.286 10.857V0H6zm14.571 10.857l-3.429 3.429H13.714l-3 3v-3H6.857V1.714H20.57v9.143z"/>
                                                     </svg>
                                                 </flux:avatar>
                                             </flux:tooltip>
                                         @endif

                                         @if($team->website_url)
                                             <flux:tooltip content="{{ $team->website_url }}">
                                                 <flux:avatar circle src="https://unavatar.io/google/{{ parse_url($team->website_url, PHP_URL_HOST) }}" />
                                             </flux:tooltip>
                                         @endif

                                         @if($team->other_social_links)
                                             <flux:tooltip content="{{ $team->other_social_links['url'] }}">
                                                 <flux:avatar circle src="https://unavatar.io/google/{{ parse_url($team->other_social_links['url'], PHP_URL_HOST) }}" />
                                             </flux:tooltip>
                                         @endif
                                     </flux:avatar.group>

                                     <div>
                                         <flux:modal.trigger name="social-links">
                                             <flux:button variant="filled">
                                                 {{ __('Edit Social Links') }}
                                             </flux:button>
                                         </flux:modal.trigger>
                                     </div>
                                 @else
                                     <flux:callout icon="link" variant="secondary">
                                         <flux:callout.heading>{{ __('Add Social Links') }}</flux:callout.heading>
                                         <flux:callout.text>
                                             {{ __('Connect your social media profiles and website to your public profile to help visitors find and follow you.') }}
                                         </flux:callout.text>
                                         <x-slot name="actions">
                                             <flux:modal.trigger name="social-links">
                                                 <flux:button>{{ __('Add Social Links') }}</flux:button>
                                             </flux:modal.trigger>
                                         </x-slot>
                                     </flux:callout>
                                  @endif
                             </div>

                            <flux:modal name="social-links" variant="flyout" class="md:w-[32rem]">
                                <div class="space-y-6">
                                    <div>
                                        <flux:heading size="lg">{{ __('Edit Social Links') }}</flux:heading>
                                        <flux:text class="mt-2">{{ __('Add your social media profiles and website to your public profile.') }}</flux:text>
                                    </div>

                                    <form wire:submit="saveSocialLinks" class="space-y-4">
                                        <flux:field>
                                            <flux:label>{{ __('Instagram') }}</flux:label>
                                            <flux:input wire:model="socialLinksForm.instagram" placeholder="username" />
                                            <flux:description>{{ __('Enter your Instagram username (without @)') }}</flux:description>
                                            <flux:error name="socialLinksForm.instagram" />
                                        </flux:field>

                                        <flux:field>
                                            <flux:label>{{ __('YouTube') }}</flux:label>
                                            <flux:input wire:model="socialLinksForm.youtube" placeholder="channel/UC123 or @username" />
                                            <flux:description>{{ __('Enter your YouTube channel URL path or @username') }}</flux:description>
                                            <flux:error name="socialLinksForm.youtube" />
                                        </flux:field>

                                        <flux:field>
                                            <flux:label>{{ __('Facebook') }}</flux:label>
                                            <flux:input wire:model="socialLinksForm.facebook" placeholder="username" />
                                            <flux:description>{{ __('Enter your Facebook username or page name') }}</flux:description>
                                            <flux:error name="socialLinksForm.facebook" />
                                        </flux:field>

                                        <flux:field>
                                            <flux:label>{{ __('X (Twitter)') }}</flux:label>
                                            <flux:input wire:model="socialLinksForm.x" placeholder="username" />
                                            <flux:description>{{ __('Enter your X (Twitter) username (without @)') }}</flux:description>
                                            <flux:error name="socialLinksForm.x" />
                                        </flux:field>

                                        <flux:field>
                                            <flux:label>{{ __('TikTok') }}</flux:label>
                                            <flux:input wire:model="socialLinksForm.tiktok" placeholder="username" />
                                            <flux:description>{{ __('Enter your TikTok username (without @)') }}</flux:description>
                                            <flux:error name="socialLinksForm.tiktok" />
                                        </flux:field>

                                        <flux:field>
                                            <flux:label>{{ __('Twitch') }}</flux:label>
                                            <flux:input wire:model="socialLinksForm.twitch" placeholder="username" />
                                            <flux:description>{{ __('Enter your Twitch username') }}</flux:description>
                                            <flux:error name="socialLinksForm.twitch" />
                                        </flux:field>

                                        <flux:field>
                                            <flux:label>{{ __('Website') }}</flux:label>
                                            <flux:input wire:model="socialLinksForm.website" placeholder="https://example.com" />
                                            <flux:description>{{ __('Enter your website URL') }}</flux:description>
                                            <flux:error name="socialLinksForm.website" />
                                        </flux:field>

                                        <flux:field>
                                            <flux:label>{{ __('Other') }}</flux:label>
                                            <div class="flex gap-2">
                                                <flux:input wire:model="socialLinksForm.other.label" placeholder="Label" class="flex-1" />
                                                <flux:input wire:model="socialLinksForm.other.url" placeholder="https://example.com" class="flex-1" />
                                            </div>
                                            <flux:error name="socialLinksForm.other.label" />
                                            <flux:error name="socialLinksForm.other.url" />
                                        </flux:field>

                                        <div class="flex gap-2 pt-4">
                                            <flux:spacer />
                                            <flux:modal.close>
                                                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                                            </flux:modal.close>
                                            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                                        </div>
                                    </form>
                                </div>
                            </flux:modal>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        @assets
            <link rel="stylesheet" type="text/css" href="https://unpkg.com/trix@2.0.8/dist/trix.css" />
            <script type="text/javascript" src="https://unpkg.com/trix@2.0.8/dist/trix.umd.min.js"></script>
            <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/sort@3.x.x/dist/cdn.min.js"></script>
        @endassets
    @endvolt
</x-app-layout>
