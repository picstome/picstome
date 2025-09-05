<?php

use App\Livewire\Forms\PublicProfileForm;
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

    public function save()
    {
        $this->form->update();

        $this->team = $this->team->fresh();

        $this->redirectRoute('branding.public-profile');
    }

    public function mount()
    {
        $this->team = Auth::user()->currentTeam;

        $this->form->setTeam($this->team);
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

                    <div class="mt-5 w-full max-w-lg">
                        <form wire:submit="save" class="space-y-6">
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

                            <flux:field>
                                <flux:label>{{ __('Instagram') }}</flux:label>
                                <flux:input wire:model="form.instagram" placeholder="username" />
                                <flux:description>{{ __('Enter your Instagram username (without @)') }}</flux:description>
                                <flux:error name="form.instagram" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('YouTube') }}</flux:label>
                                <flux:input wire:model="form.youtube" placeholder="channel/UC123 or @username" />
                                <flux:description>{{ __('Enter your YouTube channel URL path or @username') }}</flux:description>
                                <flux:error name="form.youtube" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Facebook') }}</flux:label>
                                <flux:input wire:model="form.facebook" placeholder="username" />
                                <flux:description>{{ __('Enter your Facebook username or page name') }}</flux:description>
                                <flux:error name="form.facebook" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('X (Twitter)') }}</flux:label>
                                <flux:input wire:model="form.x" placeholder="username" />
                                <flux:description>{{ __('Enter your X (Twitter) username (without @)') }}</flux:description>
                                <flux:error name="form.x" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('TikTok') }}</flux:label>
                                <flux:input wire:model="form.tiktok" placeholder="username" />
                                <flux:description>{{ __('Enter your TikTok username (without @)') }}</flux:description>
                                <flux:error name="form.tiktok" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Twitch') }}</flux:label>
                                <flux:input wire:model="form.twitch" placeholder="username" />
                                <flux:description>{{ __('Enter your Twitch username') }}</flux:description>
                                <flux:error name="form.twitch" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Website') }}</flux:label>
                                <flux:input wire:model="form.website" placeholder="https://example.com" />
                                <flux:description>{{ __('Enter your website URL') }}</flux:description>
                                <flux:error name="form.website" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Other') }}</flux:label>
                                <div class="flex gap-2">
                                    <flux:input wire:model="form.other.label" placeholder="Label" class="flex-1" />
                                    <flux:input wire:model="form.other.url" placeholder="https://example.com" class="flex-1" />
                                </div>
                                <flux:error name="form.other.label" />
                                <flux:error name="form.other.url" />
                            </flux:field>

                            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        </form>
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