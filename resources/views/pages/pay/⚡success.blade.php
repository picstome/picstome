<?php

use App\Models\Photoshoot;
use App\Models\Team;
use App\Services\FakePaymentService;
use App\Services\PaymentLinkService;
use Facades\App\Services\StripeConnectService;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Layout('layouts.guest')]
class extends Component
{
    public Team $team;

    public ?Photoshoot $photoshoot = null;

    public array $checkoutSession = [];

    #[Url]
    public ?string $session_id = null;

    #[Url]
    public ?bool $fake = null;

    public function mount(string $handle)
    {
        $this->team = Team::where('handle', $handle)->firstOrFail();

        if ($this->fake && FakePaymentService::enabled()) {
            return;
        }

        abort_if(! $this->session_id, 404);

        $this->checkoutSession = StripeConnectService::getCheckoutSession($this->team, $this->session_id);

        abort_if(($this->checkoutSession['payment_status'] ?? null) !== 'paid', 404);

        $payment = app(PaymentLinkService::class)->recordCard($this->team, $this->checkoutSession);

        $this->photoshoot = $payment->photoshoot;
    }

    public function rendering(View $view): void
    {
        $view->title(__('Payment Successful for :team', ['team' => $this->team->name]));
    }
} ?>

<div class="flex min-h-screen items-center justify-center px-4">
    <div class="mx-auto w-full max-w-md text-center">
        <div class="space-y-4">
            <a href="{{ route('handle.show', ['handle' => $team->handle]) }}" class="block space-y-4" wire:navigate>
                @if ($team->brand_logo_icon_url)
                    <img
                        src="{{ $team->brand_logo_icon_url.'&w=256&h=256' }}"
                        class="mx-auto size-32"
                        alt="{{ $team->name }}"
                    />
                @else
                    <x-ts-heading size="xl">{{ $team->name }}</x-ts-heading>
                @endif
            </a>

            <div>
                <x-ts-heading size="xl">{{ __('Payment Successful!') }}</x-ts-heading>
                <x-ts-text class="mt-2">
                    {{ __('Thank you for your payment. Your transaction was completed successfully.') }}
                </x-ts-text>
            </div>
        </div>
    </div>
</div>
