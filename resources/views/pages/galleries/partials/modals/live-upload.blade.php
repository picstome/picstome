    <x-ts-modal name="live-upload" class="w-full sm:max-w-lg">
        <div class="space-y-6">
            <div>
                <x-ts-heading size="lg">{{ __('Camera upload') }}</x-ts-heading>
                <x-ts-subheading>
                    {{ __('Set your camera up once — every shot lands in the gallery receiving uploads.') }}
                </x-ts-subheading>
            </div>

            @if ($this->remainingStorageBytes !== null && $this->remainingStorageBytes <= 0)
                <x-ts-callout icon="bolt" variant="warning">
                    <x-ts-callout-heading>{{ __('Camera uploads are paused') }}</x-ts-callout-heading>
                    <x-ts-callout-text>
                        {{ __('You have no storage left, so new shots cannot be imported. Free up space or upgrade your plan to keep shooting.') }}
                    </x-ts-callout-text>
                    <x-slot name="actions">
                        <x-ts-button :href="route('subscribe')" variant="primary">
                            {{ __('Upgrade') }}
                        </x-ts-button>
                    </x-slot>
                </x-ts-callout>
            @endif

            @if ($this->ftpAccount && ! $this->ftpAccount->routesTo($gallery) && $this->ftpAccount->activeGallery)
                <x-ts-callout
                    icon="exclamation-triangle"
                    variant="warning"
                    :text="__('Photos currently upload to :gallery.', ['gallery' => $this->ftpAccount->activeGallery->name])"
                />
            @endif

            <x-ts-switch
                wire:click="toggleFtpUploads"
                :checked="$this->ftpAccount?->routesTo($gallery)"
                :label="__('Receive photos in this gallery')"
                :disabled="$this->remainingStorageBytes !== null && $this->remainingStorageBytes <= 0"
            />

            <x-ts-switch
                wire:model.live="keepOriginalSize"
                :label="__('Keep photos at their original size')"
                :description="__('Applies to photos uploaded from now on. Photos already uploaded keep their current size.')"
            />

            @if ($this->ftpAccount)
                <div class="space-y-4">
                    <x-ts-input :value="config('picstome.ftp_uploads.host')" :label="__('Host')" readonly copyable />

                    <div class="grid grid-cols-2 gap-4">
                        <x-ts-input
                            :value="config('picstome.ftp_uploads.sftp_port')"
                            :label="__('SFTP port')"
                            readonly
                            copyable
                        />

                        <x-ts-input
                            :value="config('picstome.ftp_uploads.ftp_port')"
                            :label="__('FTP port')"
                            readonly
                            copyable
                        />
                    </div>

                    <x-ts-input :value="$this->ftpAccount->username" :label="__('Username')" readonly copyable />

                    <x-ts-field>
                        <x-ts-label>{{ __('Password') }}</x-ts-label>
                        <div class="flex gap-2">
                            <x-ts-input
                                class="flex-1"
                                type="{{ $showFtpPassword ? 'text' : 'password' }}"
                                :value="$this->ftpAccount->password"
                                readonly
                                copyable
                            />
                            <x-ts-button
                                variant="subtle"
                                icon="{{ $showFtpPassword ? 'eye-slash' : 'eye' }}"
                                wire:click="toggleFtpPassword"
                            />
                        </div>
                    </x-ts-field>

                    @if ($this->ftpAccount->last_upload_at)
                        <x-ts-callout
                            icon="check-circle"
                            variant="success"
                            :heading="__('Last photo received')"
                            :text="$this->ftpAccount->last_upload_at->diffForHumans()"
                        />
                    @else
                        <x-ts-callout
                            icon="clock"
                            variant="secondary"
                            :text="__('Waiting for the first photo. It will appear here within a minute of shooting.')"
                        />
                    @endif

                    <div>
                        <x-ts-heading size="sm">{{ __('Camera setup') }}</x-ts-heading>

                        <x-timeline class="mt-2" compact>
                            <x-timeline.items
                                :title="__('Connect to Wi-Fi')"
                                :description="__('Use a regular Wi-Fi network with internet. A phone hotspot may be slower.')"
                                icon="wifi"
                            />
                            <x-timeline.items
                                :title="__('Add the FTP server')"
                                :description="__('In your camera network menu, add a server with the settings above.')"
                                icon="server-stack"
                            />
                            <x-timeline.items
                                :title="__('Send JPEGs automatically')"
                                :description="__('Enable auto send. Shoot RAW+JPEG and send the JPEG only for the fastest uploads.')"
                                icon="arrow-up-tray"
                            />
                            <x-timeline.items
                                :title="__('Take a test shot')"
                                :description="__('New photos appear in this gallery automatically within a minute.')"
                                icon="camera"
                            />
                        </x-timeline>
                    </div>
                </div>
            @endif
        </div>
    </x-ts-modal>
