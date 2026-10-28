<?php

use App\Traits\InteractsWithTallStackModals;
use App\Models\Signature;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.guest')] class extends Component
{
    use InteractsWithTallStackModals;
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
        if ($this->signature->isSigned()) {
            abort(410);
        }

        $this->ensureIsNotRateLimited();

        $this->validate();

        $this->signature->update([
            'email' => $this->email,
            'role' => $this->role,
            'legal_name' => $this->legalName,
            'document_number' => $this->documentNumber,
            'nationality' => $this->nationality,
            'birthday' => $this->birthday,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $team = $this->signature->contract->team;

        $customer = $team->customers()->where('email', $this->email)->first();

        $photoshootCustomer = $this->signature->contract->photoshoot?->customer;
        $isClientSigning = $this->email !== $team->owner->email
            && $this->signature->contract->signatures()->count() === 2;

        if (! $customer && $photoshootCustomer && $isClientSigning) {
            $customer = $photoshootCustomer;
        }

        if ($customer) {
            if ($isClientSigning && $customer->is($photoshootCustomer)) {
                $customer->update([
                    'email' => $this->email,
                    'birthdate' => $this->birthday,
                ]);
            } elseif (empty($customer->birthdate)) {
                $customer->update(['birthdate' => $this->birthday]);
            }
        } else {
            $team->customers()->create([
                'name' => $this->legalName,
                'email' => $this->email,
                'birthdate' => $this->birthday,
            ]);
        }

        $this->signature->updateSignatureImage($this->signature_image);

        $this->signature->markAsSigned();

        if ($this->signature->contract->signaturesRemaining() === 0) {
            $this->signature->contract->execute();
        }

        $this->closeModal('sign');
    }

    protected function ensureIsNotRateLimited(): void
    {
        $key = 'signature-sign:'.$this->signature->ulid.':'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, $maxAttempts = 5)) {
            throw ValidationException::withMessages([
                'signing' => __('Too many attempts. Please try again in :seconds seconds.', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }

        RateLimiter::hit($key, 60);
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

<div class="mx-auto max-w-4xl">
    <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
        <div class="max-sm:w-full sm:flex-1">
            <div class="flex items-center gap-4">
                <x-ts-heading level="1" size="xl">{{ $signature->contract->title }}</x-ts-heading>
                @if ($signature->isSigned())
                    <x-ts-badge color="lime">{{ __('Signed') }}</x-ts-badge>
                @endif
            </div>
            <x-ts-subheading class="mt-2">{{ $signature->contract->description }}</x-ts-subheading>
        </div>
        <div class="flex gap-4">
            @unless ($signature->isSigned())
                <x-ts-modal-trigger name="sign">
                    <x-ts-button variant="primary">
                        {{ __('Sign contract') }}
                    </x-ts-button>
                </x-ts-modal-trigger>
            @endunless
        </div>
    </div>

    <div class="mt-12">
        <x-ts-heading level="2">{{ __('Details') }}</x-ts-heading>
        <x-ts-separator class="mt-4" />
        <x-description.list>
            <x-description.term>{{ __('Signature number') }}</x-description.term>
            <x-description.details>
                <span class="font-mono">{{ $signature->ulid }}</span>
            </x-description.details>
            <x-description.term>{{ __('Location') }}</x-description.term>
            <x-description.details>{{ $signature->contract->location }}</x-description.details>

            <x-description.term>{{ __('Shooting date') }}</x-description.term>
            <x-description.details>
                {{ $signature->contract->formatted_shooting_date }}
            </x-description.details>

            <x-description.term>{{ __('Contract terms') }}</x-description.term>
            <x-description.details>
                <div class="prose prose-sm">
                    {!! str()->of($signature->contract->markdown_body)->markdown() !!}
                </div>
            </x-description.details>
        </x-description.list>
    </div>

    @if (! $signature->isSigned())
        <x-ts-modal name="sign" class="w-full sm:max-w-lg">
            <form wire:submit="sign" class="space-y-6">
                <div>
                    <x-ts-heading size="lg">{{ __('Submit signature') }}</x-ts-heading>
                    <x-ts-subheading>{{ __('Enter your personal details and signature.') }}</x-ts-subheading>
                </div>

                <x-ts-select wire:model="role" :label="__('Role')" :placeholder="__('Select a role')">
                    <option value="Model">{{ __('Model') }}</option>
                    <option value="Client">{{ __('Client') }}</option>
                    <option value="Photographer">{{ __('Photographer') }}</option>
                    <option value="Make Up Artist">{{ __('Make Up Artist') }}</option>
                    <option value="Stylist">{{ __('Stylist') }}</option>
                    <option value="Parent">{{ __('Parent') }}</option>
                    <option value="Others">{{ __('Others') }}</option>
                </x-ts-select>

                <x-ts-input wire:model="legalName" :label="__('Legal name')" type="text" />

                <x-ts-input wire:model="documentNumber" :label="__('Document number')" type="text" />

                <div class="grid grid-cols-2 gap-5">
                    <x-ts-input wire:model="nationality" :label="__('Nationality')" type="text" />

                    <x-ts-input wire:model="birthday" :label="__('Birthday')" type="date" />
                </div>

                <x-ts-input wire:model="email" :label="__('Email')" type="email" />

                <x-ts-field x-data="signaturePad">
                    <x-ts-label>{{ __('Sign bellow') }}</x-ts-label>

                    <div

                        class="block w-full touch-none overflow-hidden rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white shadow-xs dark:border-white/10 dark:bg-white/10 dark:text-zinc-300 dark:shadow-none"
                    >
                        <canvas x-ref="canvas"></canvas>
                    </div>

                    <x-ts-error name="signature_image" />

                    <div x-show="$wire.signature_image" class="mt-2">
                        <x-ts-button type="button" @click="clearSignature" size="sm">
                            {{ __('Clear') }}
                        </x-ts-button>
                    </div>
                </x-ts-field>

                <x-ts-error name="signing" />

                <div class="flex">
                    <x-ts-spacer />

                    <x-ts-button type="submit" variant="primary">{{ __('Submit') }}</x-ts-button>
                </div>
            </form>
        </x-ts-modal>

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

                    clearSignature() {
                        if (this.signaturePad) {
                            this.signaturePad.clear();
                            this.$wire.signature_image = null;
                        }
                    },
                }));
            </script>
        @endscript
    @endif
</div>
