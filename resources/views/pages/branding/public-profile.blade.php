<?php

use App\Livewire\Forms\PublicProfileForm;
use App\Livewire\Forms\SocialLinksForm;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('branding.public-profile');

middleware('auth');

new class extends Component
{
    public Team $team;

    public PublicProfileForm $form;
    public SocialLinksForm $socialLinksForm;

    public function save()
    {
        $this->form->update();

        $this->team = $this->team->fresh();

        $this->redirectRoute('branding.public-profile');
    }

    public function saveSocialLinks()
    {
        $this->socialLinksForm->update();

        $this->team = $this->team->fresh();

        $this->modal('social-links')->close();

        $this->dispatch('social-links-updated');
    }

    public function mount()
    {
        $this->team = Auth::user()->currentTeam;

        $this->form->setTeam($this->team);
        $this->socialLinksForm->setTeam($this->team);
    }
}; ?>

<x-app-layout>
    @volt('pages.branding.public-profile')
        <section class="mx-auto max-w-6xl">
            @include('partials.branding-header')

            <div class="flex items-start max-md:flex-col">
                <div class="mr-10 w-full pb-4 md:w-[220px]">
                    @include('partials.branding-nav')
                </div>

                <flux:separator class="md:hidden" />

                <div class="flex-1 self-stretch max-md:pt-6">
                    <flux:heading>{{ __('Public Profile') }}</flux:heading>
                    <flux:subheading>{{ __('Configure your public profile information.') }}</flux:subheading>

                    <div class="mt-5 space-y-8">
                        <!-- Bio Section -->
                        <div class="w-full max-w-lg">
                            <form wire:submit="save" class="space-y-4">
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

                        <!-- Social Links Section -->
                        <div class="w-full max-w-lg">
                            <div class="space-y-4">
                                 <div>
                                     <flux:heading size="sm">{{ __('Social Links') }}</flux:heading>
                                     <flux:text class="mt-1">{{ __('Manage your social media profiles and website.') }}</flux:text>
                                 </div>

                                @if($team->instagram_url || $team->youtube_url || $team->facebook_url || $team->x_url || $team->tiktok_url || $team->twitch_url || $team->website_url || $team->other_social_links)
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
                                                <flux:avatar circle color="auto">
                                                    <flux:icon.globe-alt class="w-6 h-6" />
                                                </flux:avatar>
                                            </flux:tooltip>
                                        @endif

                                        @if($team->other_social_links)
                                            <flux:tooltip content="{{ $team->other_social_links['url'] }}">
                                                <flux:avatar circle color="auto" name="{{ $team->other_social_links['label'] }}" />
                                            </flux:tooltip>
                                        @endif
                                    </flux:avatar.group>
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

                                 <div>
                                     <flux:modal.trigger name="social-links">
                                         <flux:button variant="filled">
                                             {{ __('Edit Social Links') }}
                                         </flux:button>
                                     </flux:modal.trigger>
                                 </div>
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
                                            <flux:button type="submit" variant="primary">{{ __('Save Changes') }}</flux:button>
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
        @endassets
    @endvolt
</x-app-layout>
