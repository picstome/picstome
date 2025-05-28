<?php

use App\Models\Signature;
use Flux\Flux;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

use function Laravel\Folio\name;

name('signatures.sign');

new class extends Component
{
    use WithFileUploads;

    public Signature $signature;

    #[Validate('required')]
    public ?string $role = '';

    #[Validate('required')]
    public ?string $legalName = null;

    #[Validate('required')]
    public ?string $documentNumber = null;

    #[Validate('required')]
    public ?string $nationality = null;

    #[Validate('required|date')]
    public ?string $birthday = null;

    #[Validate('required|email')]
    public ?string $email = null;

    #[Validate('required|image')]
    public ?UploadedFile $signature_image = null;

    public function mount()
    {
        if (Auth::check()) {
            $this->email = Auth::user()->email;

            $this->setLastSignature();
        }
    }

    public function sign()
    {
        $this->validate();

        $this->signature->update([
            'email' => $this->email,
            'role' => $this->role,
            'legal_name' => $this->legalName,
            'document_number' => $this->documentNumber,
            'nationality' => $this->nationality,
            'birthday' => $this->birthday,
            'email' => $this->email,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $this->signature->updateSignatureImage($this->signature_image);

        $this->signature->markAsSigned();

        if ($this->signature->contract->signaturesRemaining() === 0) {
            $this->signature->contract->execute();
        }

        Flux::modals()->close();
    }

    protected function setLastSignature()
    {
        $signature = Signature::signed()->where('email', Auth::user()->email)->latest()->first();

        $this->fill([
            'role' => $signature?->role,
            'legalName' => $signature?->legal_name,
            'nationality' => $signature?->nationality,
            'documentNumber' => $signature?->document_number,
            'birthday' => $signature?->birthday?->isoFormat('YYYY-MM-DD'),
            'email' => $signature?->email,
        ]);
    }
}; ?>

<x-guest-layout>
    @volt('pages.signatures.sign')
        <div class="mx-auto max-w-4xl">
            <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
                <div class="max-sm:w-full sm:flex-1">
                    <div class="flex items-center gap-4">
                        <x-heading level="1" size="xl">{{ $signature->contract->title }}</x-heading>
                        @if ($signature->isSigned())
                            <flux:badge color="lime">{{ __('Signed') }}</flux:badge>
                        @endif
                    </div>
                    <x-subheading class="mt-2">{{ $signature->contract->description }}</x-subheading>
                </div>
                <div class="flex gap-4">
                    @unless ($signature->isSigned())
                        <flux:modal.trigger name="sign">
                            <flux:button variant="primary">
                                {{ __('Sign contract') }}
                            </flux:button>
                        </flux:modal.trigger>
                    @endunless
                </div>
            </div>

            <div class="mt-12">
                <flux:heading level="2">{{ __('Details') }}</flux:heading>
                <flux:separator class="mt-4" />
                <x-description.list>
                    <x-description.term>{{ __('Signature number') }}</x-description.term>
                    <x-description.details>
                        <span class="font-mono">{{ $signature->ulid }}</span>
                    </x-description.details>
                    <x-description.term>{{ __('Location') }}</x-description.term>
                    <x-description.details>{{ $signature->contract->location }}</x-description.details>

                    <x-description.term>{{ __('Shooting date') }}</x-description.term>
                    <x-description.details>{{ $signature->contract->shooting_date }}</x-description.details>

                    <x-description.term>{{ __('Contract terms') }}</x-description.term>
                    <x-description.details>
                        <div class="prose prose-sm">
                            {!! str()->of($signature->contract->markdown_body)->markdown() !!}
                        </div>
                    </x-description.details>
                </x-description.list>
            </div>

            @if (! $signature->isSigned())
                <flux:modal name="sign" class="w-full sm:max-w-lg">
                    <form wire:submit="sign" class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('Submit signature') }}</flux:heading>
                            <flux:subheading>{{ __('Enter your personal details and signature.') }}</flux:subheading>
                        </div>

                        <flux:select wire:model="role" :label="__('Role')" :placeholder="__('Select a role')">
                            <option value="Model">{{ __('Model') }}</option>
                            <option value="Client">{{ __('Client') }}</option>
                            <option value="Photographer">{{ __('Photographer') }}</option>
                            <option value="Make Up Artist">{{ __('Make Up Artist') }}</option>
                            <option value="Stylist">{{ __('Stylist') }}</option>
                            <option value="Parent">{{ __('Parent') }}</option>
                            <option value="Others">{{ __('Others') }}</option>
                        </flux:select>

                        <flux:input wire:model="legalName" :label="__('Legal name')" type="text" />

                        <flux:input wire:model="documentNumber" :label="__('Document number')" type="text" />

                        <div class="grid grid-cols-2 gap-5">
                            <flux:input wire:model="nationality" :label="__('Nationality')" type="text" />

                            <flux:input wire:model="birthday" :label="__('Birthday')" type="date" />
                        </div>

                        <flux:input wire:model="email" :label="__('Email')" type="email" />

                        <flux:field>
                            <flux:label>{{ __('Sign bellow') }}</flux:label>

                            <div
                                x-data="signaturePad"
                                data-flux-control
                                class="block touch-none w-full overflow-hidden rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white shadow-xs dark:border-white/10 dark:bg-white/10 dark:text-zinc-300 dark:shadow-none"
                            >
                                <canvas x-ref="canvas"></canvas>
                            </div>

                            <flux:error name="signature_image" />
                        </flux:field>

                        <div class="flex">
                            <flux:spacer />

                            <flux:button type="submit" variant="primary">{{ __('Submit') }}</flux:button>
                        </div>
                    </form>
                </flux:modal>

                @assets
                    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
                @endassets

                @script
                    <script>
                        Alpine.data('signaturePad', () => ({
                            signaturePad: null,

                            init() {
                                let canvas = this.$refs.canvas;
                                this.signaturePad = new SignaturePad(canvas, {
                                    backgroundColor: 'rgb(255, 255, 255)',
                                });

                                this.signaturePad.addEventListener('endStroke', () => {
                                    this.saveSignature();
                                });
                            },

                            async saveSignature() {
                                if (this.signaturePad.isEmpty()) {
                                    return;
                                }

                                const dataUrl = this.signaturePad.toDataURL();
                                const blob = await fetch(dataUrl).then((res) => res.blob());
                                const file = new File([blob], 'signature.png', { type: 'image/png' });

                                this.$wire.upload('signature_image', file);
                            },
                        }));
                    </script>
                @endscript
            @endif
        </div>
    @endvolt
</x-guest-layout>
