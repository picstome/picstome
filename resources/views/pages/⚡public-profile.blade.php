<?php

use App\Traits\InteractsWithTallStackModals;
use TallStackUi\Traits\Interactions;
use App\Livewire\Forms\BioLinkForm;
use App\Livewire\Forms\PublicProfileForm;
use App\Livewire\Forms\SocialLinksForm;
use App\Models\BioLink;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use InteractsWithTallStackModals;
    use Interactions;
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

        $this->toast()->success(__('Your changes have been saved.'))->send();
    }

    public function saveSocialLinks()
    {
        $this->socialLinksForm->update();

        $this->team = $this->team->fresh();

        $this->closeModal('social-links');

        $this->dispatch('social-links-updated');

        $this->toast()->success(__('Your changes have been saved.'))->send();
    }

    public function addLink()
    {
        $this->addForm->store();
        $this->addForm->resetForm();
        $this->closeModal('add-link');
    }

    public function updateLink(BioLink $link)
    {
        $this->authorize('update', $link);

        $this->editForm->update();

        $this->editForm->resetForm();
        $this->editingLink = null;
        $this->closeModal('edit-link');
    }

    public ?int $pendingLinkId = null;

    public function requestDeleteLink(int $id): void
    {
        $this->pendingLinkId = $id;

        $this->openModal('delete-link');
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
        $this->openModal('edit-link');
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

<section class="mx-auto max-w-lg">
    <div class="relative mb-6 w-full">
        <x-ts-heading size="xl" level="1">{{ __('Public Profile') }}</x-ts-heading>
        <x-ts-subheading size="lg">
            {{ __('Configure your public profile information.') }}
        </x-ts-subheading>
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <x-ts-button
                :href="route('handle.show', ['handle' => $team->handle])"
                target="_blank"
                icon:trailing="arrow-top-right-on-square"
                variant="primary"
                class="btn-ink"
                size="sm"
            >
                {{ __('View Profile') }}
            </x-ts-button>

            <x-ts-button
                :href="route('branding.styling')"
                icon="paint-brush"
                variant="primary"
                class="btn-ink"
                size="sm"
                wire:navigate
            >
                {{ __('Styling') }}
            </x-ts-button>
        </div>
        <x-ts-separator variant="subtle" class="mt-6" />
    </div>

    <div class="mt-5 space-y-12">
        <!-- Handle Section -->
        <form wire:submit="save" class="space-y-4">
            <x-ts-field>
                <x-ts-label>{{ __('Username') }}</x-ts-label>
                <x-ts-input wire:model="form.handle" :placeholder="__('e.g. mystudio')" />
                <x-ts-description>
                    {{ __('This is your unique username that visitors will use to access your public profile at @').$team->handle }}
                </x-ts-description>

            </x-ts-field>

            <x-ts-field
                class="dark:**:[.trix-button]:bg-white! **:[.trix-button-group--file-tools]:!hidden **:[.trix-button-group--history-tools]:!hidden dark:**:[trix-editor]:border-white/10! dark:**:[trix-editor]:bg-white/10! **:[trix-toolbar]:sticky **:[trix-toolbar]:top-0 **:[trix-toolbar]:z-10 **:[trix-toolbar]:bg-white dark:**:[trix-toolbar]:bg-zinc-800"
            >
                <x-ts-label>{{ __('Bio') }}</x-ts-label>

                <trix-editor
                    input="trix"
                    x-init="$nextTick(() => $el.editor.loadHTML($wire.form.bio))"
                    x-on:trix-change="$wire.form.bio = $event.target.value"
                    class="prose prose-sm dark:prose-invert mt-2"
                ></trix-editor>

                <input wire:model="form.bio" id="trix" type="text" class="hidden" />

                <x-ts-description>
                    {{ __('A short description that appears on your public profile. Maximum 1000 characters.') }}
                </x-ts-description>

                <x-ts-error name="form.bio" />
            </x-ts-field>

            <x-ts-button type="submit" variant="primary">{{ __('Save') }}</x-ts-button>
        </form>

        <!-- Bio Links Section -->
        <div>
            <div class="space-y-4">
                <div>
                    <x-ts-heading size="sm">{{ __('Bio Links') }}</x-ts-heading>
                    <x-ts-text class="mt-1">{{ __('Manage your bio links for your public profile.') }}</x-ts-text>
                </div>

                <div>
                    <x-ts-separator variant="subtle" />
                    @if ($this->bioLinks->isNotEmpty())
                        <x-data-table>
                            <x-data-table.rows x-sort="handleReorder">
                                @foreach ($this->bioLinks as $link)
                                    <x-data-table.row :key="$link->id" x-sort:item="{{ $link->id }}">
                                        <x-data-table.cell>
                                            <div class="flex items-center gap-3">
                                                <x-ts-button
                                                    x-sort:handle
                                                    variant="subtle"
                                                    size="sm"
                                                    inset="top bottom"
                                                    class="cursor-move touch-manipulation"
                                                    square
                                                >
                                                    <x-icon name="bars-2" variant="micro" />
                                                </x-ts-button>
                                                <div>
                                                    <x-ts-text variant="strong">
                                                        {{ str()->of($link->title)->limit(40) }}
                                                    </x-ts-text>
                                                    <x-ts-text class="text-xs">
                                                        {{ str()->of($link->url)->limit(40) }}
                                                    </x-ts-text>
                                                </div>
                                            </div>
                                        </x-data-table.cell>
                                        <x-data-table.cell>
                                            <x-dropdown position="bottom-end">
                                                <x-slot:action>
                                                    <x-ts-button
                                                        icon="ellipsis-vertical"
                                                        variant="ghost"
                                                        size="sm"
                                                        x-on:click="show = ! show"
                                                    />
                                                </x-slot:action>

                                                <x-dropdown.items
                                                    :text="__('Edit')"
                                                    wire:click="editLink({{ $link->id }})"
                                                />
                                                <x-dropdown.items
                                                    :text="__('Delete')"
                                                    wire:click="requestDeleteLink({{ $link->id }})"
                                                    class="text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                                                />
                                            </x-dropdown>
                                        </x-data-table.cell>
                                    </x-data-table.row>
                                @endforeach
                            </x-data-table.rows>
                        </x-data-table>

                        <div class="mt-2">
                            <x-ts-modal-trigger name="add-link">
                                <x-ts-button icon="plus" variant="primary" class="btn-ink">
                                    {{ __('Add Link') }}
                                </x-ts-button>
                            </x-ts-modal-trigger>
                        </div>
                    @else
                        <x-ts-callout icon="link" variant="secondary">
                            <x-ts-callout-heading>{{ __('Add Bio Links') }}</x-ts-callout-heading>
                            <x-ts-callout-text>
                                {{ __('Add links to your bio to help visitors discover your work and connect with you.') }}
                            </x-ts-callout-text>
                            <x-slot name="actions">
                                <x-ts-modal-trigger name="add-link">
                                    <x-ts-button variant="primary" class="btn-ink">{{ __('Add Link') }}</x-ts-button>
                                </x-ts-modal-trigger>
                            </x-slot>
                        </x-ts-callout>
                    @endif
                </div>
            </div>

            <x-ts-modal name="add-link" class="md:w-96">
                <div class="space-y-6">
                    <div>
                        <x-ts-heading size="lg">{{ __('Add Bio Link') }}</x-ts-heading>
                        <x-ts-text class="mt-2">{{ __('Add a new link to your bio.') }}</x-ts-text>
                    </div>

                    <div class="space-y-4">
                        <x-ts-field>
                            <x-ts-label>{{ __('Title') }}</x-ts-label>
                            <x-ts-input wire:model="addForm.title" type="text" placeholder="e.g. Instagram" />

                        </x-ts-field>

                        <x-ts-field>
                            <x-ts-label>{{ __('URL') }}</x-ts-label>
                            <x-ts-input
                                wire:model="addForm.url"
                                type="url"
                                placeholder="https://instagram.com/username"
                            />

                        </x-ts-field>
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-ts-modal-close>
                            <x-ts-button variant="ghost">{{ __('Cancel') }}</x-ts-button>
                        </x-ts-modal-close>
                        <x-ts-button wire:click="addLink" variant="primary">{{ __('Add Link') }}</x-ts-button>
                    </div>
                </div>
            </x-ts-modal>

            <x-ts-modal name="edit-link" class="md:w-96">
                <div class="space-y-6">
                    <div>
                        <x-ts-heading size="lg">{{ __('Edit Bio Link') }}</x-ts-heading>
                        <x-ts-text class="mt-2">{{ __('Update your bio link details.') }}</x-ts-text>
                    </div>

                    <div class="space-y-4">
                        <x-ts-field>
                            <x-ts-label>{{ __('Title') }}</x-ts-label>
                            <x-ts-input wire:model="editForm.title" type="text" placeholder="e.g. Instagram" />

                        </x-ts-field>

                        <x-ts-field>
                            <x-ts-label>{{ __('URL') }}</x-ts-label>
                            <x-ts-input
                                wire:model="editForm.url"
                                type="url"
                                placeholder="https://instagram.com/username"
                            />

                        </x-ts-field>
                    </div>

                    <div class="flex justify-end gap-2">
                        <x-ts-modal-close>
                            <x-ts-button variant="ghost">{{ __('Cancel') }}</x-ts-button>
                        </x-ts-modal-close>
                        <x-ts-button wire:click="updateLink({{ $editingLink }})" variant="primary">
                            {{ __('Update Link') }}
                        </x-ts-button>
                    </div>
                </div>
            </x-ts-modal>
        </div>

        <!-- Social Links Section -->
        <div>
            <div class="space-y-4">
                <div>
                    <x-ts-heading size="sm">{{ __('Social Links') }}</x-ts-heading>
                    <x-ts-text class="mt-1">{{ __('Manage your social media profiles and website.') }}</x-ts-text>
                </div>

                @if ($team->hasSocialLinks())
                    <x-ts-avatar-group>
                        @if ($team->instagram_url)
                            <x-ts-tooltip content="{{ $team->instagram_url }}">
                                <x-ts-avatar circle src="https://s.magecdn.com/social/tc-instagram.svg" />
                            </x-ts-tooltip>
                        @endif

                        @if ($team->youtube_url)
                            <x-ts-tooltip content="{{ $team->youtube_url }}">
                                <x-ts-avatar circle src="https://s.magecdn.com/social/tc-youtube.svg" />
                            </x-ts-tooltip>
                        @endif

                        @if ($team->facebook_url)
                            <x-ts-tooltip content="{{ $team->facebook_url }}">
                                <x-ts-avatar circle src="https://s.magecdn.com/social/tc-facebook.svg" />
                            </x-ts-tooltip>
                        @endif

                        @if ($team->x_url)
                            <x-ts-tooltip content="{{ $team->x_url }}">
                                <x-ts-avatar circle src="https://s.magecdn.com/social/tc-x.svg" />
                            </x-ts-tooltip>
                        @endif

                        @if ($team->tiktok_url)
                            <x-ts-tooltip content="{{ $team->tiktok_url }}">
                                <x-ts-avatar circle src="https://s.magecdn.com/social/tc-tiktok.svg" />
                            </x-ts-tooltip>
                        @endif

                        @if ($team->twitch_url)
                            <x-ts-tooltip content="{{ $team->twitch_url }}">
                                <x-ts-avatar circle>
                                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0L1.714 4.286v15.428h5.143V24l4.286-4.286H13.714L22.286 10.857V0H6zm14.571 10.857l-3.429 3.429H13.714l-3 3v-3H6.857V1.714H20.57v9.143z"
                                        />
                                    </svg>
                                </x-ts-avatar>
                            </x-ts-tooltip>
                        @endif

                        @if ($team->website_url)
                            <x-ts-tooltip content="{{ $team->website_url }}">
                                <x-ts-avatar
                                    circle
                                    src="https://unavatar.io/google/{{ parse_url($team->website_url, PHP_URL_HOST) }}"
                                />
                            </x-ts-tooltip>
                        @endif

                        @if ($team->other_social_links)
                            <x-ts-tooltip content="{{ $team->other_social_links['url'] }}">
                                <x-ts-avatar
                                    circle
                                    src="https://unavatar.io/google/{{ parse_url($team->other_social_links['url'], PHP_URL_HOST) }}"
                                />
                            </x-ts-tooltip>
                        @endif
                    </x-ts-avatar-group>

                    <div>
                        <x-ts-modal-trigger name="social-links">
                            <x-ts-button variant="primary" class="btn-ink">
                                {{ __('Edit Social Links') }}
                            </x-ts-button>
                        </x-ts-modal-trigger>
                    </div>
                @else
                    <x-ts-callout icon="link" variant="secondary">
                        <x-ts-callout-heading>{{ __('Add Social Links') }}</x-ts-callout-heading>
                        <x-ts-callout-text>
                            {{ __('Connect your social media profiles and website to your public profile to help visitors find and follow you.') }}
                        </x-ts-callout-text>
                        <x-slot name="actions">
                            <x-ts-modal-trigger name="social-links">
                                <x-ts-button variant="primary" class="btn-ink">
                                    {{ __('Add Social Links') }}
                                </x-ts-button>
                            </x-ts-modal-trigger>
                        </x-slot>
                    </x-ts-callout>
                @endif
            </div>

            <x-ts-modal name="social-links" variant="flyout" class="md:w-[32rem]">
                <div class="space-y-6">
                    <div>
                        <x-ts-heading size="lg">{{ __('Edit Social Links') }}</x-ts-heading>
                        <x-ts-text class="mt-2">
                            {{ __('Add your social media profiles and website to your public profile.') }}
                        </x-ts-text>
                    </div>

                    <form wire:submit="saveSocialLinks" class="space-y-4">
                        <x-ts-field>
                            <x-ts-label>{{ __('Instagram') }}</x-ts-label>
                            <x-ts-input wire:model="socialLinksForm.instagram" placeholder="username" />
                            <x-ts-description>{{ __('Enter your Instagram username (without @)') }}</x-ts-description>

                        </x-ts-field>

                        <x-ts-field>
                            <x-ts-label>{{ __('YouTube') }}</x-ts-label>
                            <x-ts-input
                                wire:model="socialLinksForm.youtube"
                                placeholder="channel/UC123 or @username"
                            />
                            <x-ts-description>
                                {{ __('Enter your YouTube channel URL path or @username') }}
                            </x-ts-description>

                        </x-ts-field>

                        <x-ts-field>
                            <x-ts-label>{{ __('Facebook') }}</x-ts-label>
                            <x-ts-input wire:model="socialLinksForm.facebook" placeholder="username" />
                            <x-ts-description>{{ __('Enter your Facebook username or page name') }}</x-ts-description>

                        </x-ts-field>

                        <x-ts-field>
                            <x-ts-label>{{ __('X (Twitter)') }}</x-ts-label>
                            <x-ts-input wire:model="socialLinksForm.x" placeholder="username" />
                            <x-ts-description>
                                {{ __('Enter your X (Twitter) username (without @)') }}
                            </x-ts-description>

                        </x-ts-field>

                        <x-ts-field>
                            <x-ts-label>{{ __('TikTok') }}</x-ts-label>
                            <x-ts-input wire:model="socialLinksForm.tiktok" placeholder="username" />
                            <x-ts-description>{{ __('Enter your TikTok username (without @)') }}</x-ts-description>

                        </x-ts-field>

                        <x-ts-field>
                            <x-ts-label>{{ __('Twitch') }}</x-ts-label>
                            <x-ts-input wire:model="socialLinksForm.twitch" placeholder="username" />
                            <x-ts-description>{{ __('Enter your Twitch username') }}</x-ts-description>

                        </x-ts-field>

                        <x-ts-field>
                            <x-ts-label>{{ __('Website') }}</x-ts-label>
                            <x-ts-input wire:model="socialLinksForm.website" placeholder="https://example.com" />
                            <x-ts-description>{{ __('Enter your website URL') }}</x-ts-description>

                        </x-ts-field>

                        <x-ts-field>
                            <x-ts-label>{{ __('Other') }}</x-ts-label>
                            <div class="flex gap-2">
                                <x-ts-input
                                    wire:model="socialLinksForm.other.label"
                                    placeholder="Label"
                                    class="flex-1"
                                />
                                <x-ts-input
                                    wire:model="socialLinksForm.other.url"
                                    placeholder="https://example.com"
                                    class="flex-1"
                                />
                            </div>


                        </x-ts-field>

                        <div class="flex gap-2 pt-4">
                            <x-ts-spacer />
                            <x-ts-modal-close>
                                <x-ts-button variant="ghost">{{ __('Cancel') }}</x-ts-button>
                            </x-ts-modal-close>
                            <x-ts-button type="submit" variant="primary">{{ __('Save') }}</x-ts-button>
                        </div>
                    </form>
                </div>
            </x-ts-modal>
        </div>
    </div>

    <x-confirm-modal
        name="delete-link"
        :title="__('Delete bio link?')"
        :message="__('This action cannot be undone.')"
        :method="$this->pendingLinkId ? 'deleteLink('.$this->pendingLinkId.')' : null"
    />
</section>
@assets
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/trix@2.0.8/dist/trix.css" />
    <script type="text/javascript" src="https://unpkg.com/trix@2.0.8/dist/trix.umd.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/sort@3.x.x/dist/cdn.min.js"></script>
@endassets
