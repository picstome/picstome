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
                                <div class="flex items-center justify-between">
                                    <div>
                                        <flux:heading size="sm">{{ __('Social Links') }}</flux:heading>
                                        <flux:text class="mt-1">{{ __('Manage your social media profiles and website.') }}</flux:text>
                                    </div>
                                    <flux:modal.trigger name="social-links">
                                        <flux:button variant="outline" icon="pencil-square">
                                            {{ __('Edit Social Links') }}
                                        </flux:button>
                                    </flux:modal.trigger>
                                </div>

                                @if($team->instagram_url || $team->youtube_url || $team->facebook_url || $team->x_url || $team->tiktok_url || $team->twitch_url || $team->website_url || $team->other_social_links)
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                        @if($team->instagram_url)
                                            <flux:badge variant="soft" class="flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                                </svg>
                                                Instagram
                                            </flux:badge>
                                        @endif

                                        @if($team->youtube_url)
                                            <flux:badge variant="soft" class="flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                                </svg>
                                                YouTube
                                            </flux:badge>
                                        @endif

                                        @if($team->facebook_url)
                                            <flux:badge variant="soft" class="flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                                </svg>
                                                Facebook
                                            </flux:badge>
                                        @endif

                                        @if($team->x_url)
                                            <flux:badge variant="soft" class="flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                                </svg>
                                                X
                                            </flux:badge>
                                        @endif

                                        @if($team->tiktok_url)
                                            <flux:badge variant="soft" class="flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>
                                                </svg>
                                                TikTok
                                            </flux:badge>
                                        @endif

                                        @if($team->twitch_url)
                                            <flux:badge variant="soft" class="flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0L1.714 4.286v15.428h5.143V24l4.286-4.286H13.714L22.286 10.857V0H6zm14.571 10.857l-3.429 3.429H13.714l-3 3v-3H6.857V1.714H20.57v9.143z"/>
                                                </svg>
                                                Twitch
                                            </flux:badge>
                                        @endif

                                        @if($team->website_url)
                                            <flux:badge variant="soft" class="flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                                </svg>
                                                Website
                                            </flux:badge>
                                        @endif

                                        @if($team->other_social_links)
                                            <flux:badge variant="soft" class="flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                                </svg>
                                                {{ $team->other_social_links['label'] ?? 'Other' }}
                                            </flux:badge>
                                        @endif
                                    </div>
                                @else
                                    <flux:callout icon="link" color="blue">
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
