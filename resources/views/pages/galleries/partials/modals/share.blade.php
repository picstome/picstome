    <x-ts-modal name="share" class="w-full sm:max-w-lg">
        <form wire:submit="share" class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Share gallery') }}</x-ts-heading>
                <x-ts-subheading>{{ __('Customize your shared gallery.') }}</x-ts-subheading>
            </div>

            <x-ts-switch
                wire:model="shareForm.watermarked"
                :label="__('Watermark photos')"
                @click="$wire.shareForm.downloadable = false"
            />

            <x-ts-switch
                wire:model="shareForm.downloadable"
                :label="__('Visitors can download photos')"
                x-bind:disabled="$wire.shareForm.watermarked"
            />

            @if (config('picstome.file_versions_enabled'))
                <x-ts-switch
                    wire:model="shareForm.versionsVisible"
                    :label="__('Visitors can view and compare versions')"
                />
            @endif

            <x-ts-switch wire:model="shareForm.selectable" :label="__('Visitors can select photos')" />

            <div x-show="$wire.shareForm.selectable">
                <x-ts-switch wire:model="shareForm.limitedSelection" :label="__('Limit selection')" />
            </div>

            <div x-show="$wire.shareForm.limitedSelection">
                <x-ts-input wire:model="shareForm.selectionLimit" :label="__('Limit photo selection')" type="number" />
            </div>

            <x-ts-switch
                wire:model="shareForm.passwordProtected"
                :label="__('Protect with a password')"
            />

            <div x-show="$wire.shareForm.passwordProtected">
                <x-ts-input
                    wire:model="shareForm.password"
                    type="password"
                    :label="__('Password')"
                />
            </div>

            <x-ts-switch
                wire:model="shareForm.descriptionEnabled"
                :label="__('Add description')"
            />

            <x-ts-switch
                wire:model="shareForm.commentsEnabled"
                :label="__('Enable photo comments')"
                data-test="share-comments-toggle"
                x-on:change="if (! $event.target.checked) $wire.set('shareForm.annotationsEnabled', false)"
            />

            <x-ts-switch
                wire:model="shareForm.annotationsEnabled"
                :label="__('Enable photo annotations')"
                :description="__('Visitors can pin a comment to a spot on the photo. Enabling this also enables photo comments.')"
                data-test="share-annotations-toggle"
                x-on:change="if ($event.target.checked) $wire.set('shareForm.commentsEnabled', true)"
            />

            <div x-show="$wire.shareForm.descriptionEnabled">
                <x-ts-textarea
                    wire:model="shareForm.description"
                    :label="__('Description')"
                    :placeholder="__('Add a description for your shared gallery...')"
                    rows="3"
                />
            </div>

            <div class="flex">
                <x-ts-spacer />

                <x-ts-button type="submit" variant="primary">{{ __('Save') }}</x-ts-button>
            </div>
        </form>
    </x-ts-modal>

    <x-ts-modal name="share-link" class="w-full sm:max-w-lg">
        <div class="space-y-6">
            <x-ts-heading size="lg">{{ __('Gallery shared') }}</x-ts-heading>

            <x-ts-input
                icon="link"
                :value="route('shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug])"
                :label="__('Share URL')"
                readonly
                copyable
            />
        </div>
    </x-ts-modal>
