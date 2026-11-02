<?php

use App\Livewire\Forms\LogosForm;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use Interactions;
    use WithFileUploads;

    public Team $team;

    public LogosForm $form;

    public function save()
    {
        if (! $this->canSave()) {
            return;
        }

        $this->form->update();

        $this->team = $this->team->fresh();

        $this->toast()->success(__('Your changes have been saved.'))->send();
    }

    public function canSave(): bool
    {
        return $this->form->logo !== null || $this->form->logoIcon !== null;
    }

    public function deleteLogo(): void
    {
        $this->form->logo = null;

        $this->team->deleteBrandLogo();

        $this->team = $this->team->fresh();

        $this->toast()->success(__('The image has been deleted.'))->send();
    }

    public function deleteLogoIcon(): void
    {
        $this->form->logoIcon = null;

        $this->team->deleteBrandLogoIcon();

        $this->team = $this->team->fresh();

        $this->toast()->success(__('The image has been deleted.'))->send();
    }

    public function mount()
    {
        $this->team = Auth::user()->currentTeam;

        $this->form->setTeam($this->team);
    }
}; ?>

<section class="mx-auto max-w-6xl">
    @include('partials.branding-header')

    <div class="flex items-start max-md:flex-col">
        <div class="mr-10 w-full pb-4 md:w-[220px]">
            @include('partials.branding-nav')
        </div>

        <x-ts-separator class="md:hidden" />

        <div class="flex-1 self-stretch max-md:pt-6">
            <x-ts-heading>{{ __('Logos') }}</x-ts-heading>
            <x-ts-subheading>{{ __('Upload and manage your studio logos.') }}</x-ts-subheading>

            <div class="mt-5 w-full max-w-lg">
                <form wire:submit="save" class="space-y-6">
                    @if ($team->brand_logo_icon_url)
                        <div class="space-y-2">
                            <x-ts-label>{{ __('Current Logo Icon') }}</x-ts-label>
                            <div class="flex items-start gap-3">
                                <img src="{{ $team->brand_logo_icon_url }}" class="size-[80px]" />
                                <x-ts-modal-trigger name="delete-logo-icon">
                                    <x-ts-button icon="trash" variant="subtle" size="sm" :tooltip="__('Delete')" />
                                </x-ts-modal-trigger>
                            </div>
                        </div>
                    @endif

                    <x-branding-image-upload
                        property="form.logoIcon"
                        :label="__('Logo Icon')"
                        :description="__('Used as the favicon, app icon and avatar in your dashboard, client area and payment pages. Square 1:1, recommended 512×512 px in PNG or WebP with a transparent background.')"
                        :max-width="512"
                        :max-height="512"
                        square
                    />

                    @if ($team->brand_logo_url)
                        <div class="space-y-2">
                            <x-ts-label>{{ __('Current Logo') }}</x-ts-label>
                            <div class="flex items-start gap-3">
                                <img src="{{ $team->brand_logo_url }}" class="max-h-[80px]" />
                                <x-ts-modal-trigger name="delete-logo">
                                    <x-ts-button icon="trash" variant="subtle" size="sm" :tooltip="__('Delete')" />
                                </x-ts-modal-trigger>
                            </div>
                        </div>
                    @endif

                    <x-branding-image-upload
                        property="form.logo"
                        :label="__('Logo')"
                        :description="__('Displayed on your public portfolio and shared gallery pages. Recommended 1200×400 px in PNG or WebP with a transparent background.')"
                        :max-width="1200"
                        :max-height="400"
                    />

                    <x-ts-button
                        type="submit"
                        variant="primary"
                        :disabled="! $this->canSave()"
                        x-data="{ uploads: 0 }"
                        x-on:livewire-upload-start.window="uploads++"
                        x-on:livewire-upload-finish.window="uploads = Math.max(0, uploads - 1)"
                        x-on:livewire-upload-error.window="uploads = Math.max(0, uploads - 1)"
                        x-bind:disabled="uploads > 0 || (! $wire.form.logo && ! $wire.form.logoIcon)"
                        x-bind:class="uploads > 0 ? 'bg-amber-500! hover:bg-amber-600!' : ''"
                    >
                        <span x-show="uploads === 0">{{ __('Save') }}</span>
                        <span x-show="uploads > 0" x-cloak>{{ __('Uploading...') }}</span>
                    </x-ts-button>
                </form>
            </div>
        </div>
    </div>

    <x-confirm-modal
        name="delete-logo"
        :title="__('Delete logo?')"
        :message="__('This action cannot be undone.')"
        :confirm-label="__('Delete')"
        method="deleteLogo"
    />

    <x-confirm-modal
        name="delete-logo-icon"
        :title="__('Delete logo icon?')"
        :message="__('This action cannot be undone.')"
        :confirm-label="__('Delete')"
        method="deleteLogoIcon"
    />
</section>

@include('partials.branding-image-upload-script')
