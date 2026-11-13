<?php

use App\Models\Team;
use App\Services\FakePaymentService;
use App\Services\PaymentPlanLimitService;
use Facades\App\Services\StripeConnectService;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new
#[Layout('layouts.guest')]
class extends Component
{
    use Interactions;

    public Team $team;

    /**
     * Whether the portfolio button is shown. A boolean instead of the
     * galleries collection: the page only needs to know if any public
     * gallery exists, and loading every photo of every gallery to answer
     * that cost tens of megabytes on a large portfolio.
     */
    public bool $hasPublicGalleries = false;

    public $amount;

    public $description;

    /**
     * The photographer used up the monthly payment quota. The public profile
     * hides the pay button instead of letting the client start a checkout
     * that cannot be completed.
     */
    public bool $paymentsAvailable = true;

    public function mount(string $handle)
    {
        $this->team = Team::where('handle', strtolower($handle))->firstOrFail();

        abort_if(! $this->team, 404);

        $this->hasPublicGalleries = $this->team->galleries()->public()->exists();
        $this->paymentsAvailable = app(PaymentPlanLimitService::class)->canReceive($this->team);
    }

    public function rendering(View $view): void
    {
        $view->title($this->team->name);
    }

    public function checkout()
    {
        if (! $this->paymentsAvailable) {
            $this->toast()
                ->error(__('Payments are not available at the moment. Please contact us.'))
                ->send();

            return;
        }

        $this->validate([
            'amount' => 'required|integer|min:1',
            'description' => 'required|string|max:255',
        ]);

        if (FakePaymentService::enabled()) {
            app(FakePaymentService::class)->createPayment(
                $this->team,
                $this->amount * 100,
                $this->description
            );

            return redirect()->route('handle.pay.success', [
                'handle' => $this->team->handle,
                'fake' => 1,
            ]);
        }

        $checkoutUrl = StripeConnectService::createCheckoutSession(
            $this->team,
            route('handle.pay.success', ['handle' => $this->team->handle]).'?session_id={CHECKOUT_SESSION_ID}',
            route('handle.pay.cancel', ['handle' => $this->team->handle]),
            $this->amount * 100,
            $this->description,
        );

        return redirect()->away($checkoutUrl);
    }
}; ?>


<x-slot name="font">{{ $team->brand_font }}</x-slot>

<x-slot name="color">{{ $team->brand_color }}</x-slot>

<x-slot name="fullScreen">{{ true }}</x-slot>

<x-slot name="head">
    @if($team->brand_logo_icon_url)
        <link rel="apple-touch-icon" sizes="300x300" href="{{ $team->brand_logo_icon_url . '&w=300&h=300' }}" />
        <link rel="icon" type="image/png" sizes="300x300" href="{{ $team->brand_logo_icon_url . '&w=300&h=300' }}" />
    @endif
    <meta name="twitter:card" content="summary" />
    <meta name="twitter:image" content="{{ $team->brand_logo_icon_url ? $team->brand_logo_icon_url . '&w=300&h=300' : '' }}" />
    <meta property="og:type" content="profile" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:title" content="{{ $team->name }}" />
    <meta property="og:description" content="{{ $team->bio ?: $team->name }}" />
    <meta property="og:image" content="{{ $team->brand_logo_icon_url ? $team->brand_logo_icon_url . '&w=300&h=300' : '' }}" />
    @if(app()->environment('production'))
        @include('partials.google-analytics')
    @endif
</x-slot>

<div class="flex min-h-screen items-center justify-center px-4">
    <div class="mx-auto w-full max-w-md text-center">
        <div class="space-y-4">
            @include('partials.public-branding')
            @if($team->bio)
                <div class="prose prose-sm max-w-none dark:prose-invert">
                    {!! $team->bio !!}
                </div>
            @endif
            @if($this->hasPublicGalleries && !$team->portfolio_public_disabled)
                <div class="flex justify-center">
                    <x-ts-button
                        variant="primary"
                        :color="$team->brand_color ?? null"
                        href="{{ route('portfolio.index', ['handle' => $team->handle]) }}"
                        wire:navigate
                    >
                         {{ __('View Portfolio') }}
                    </x-ts-button>
                </div>
            @endif
        </div>

        @if($team->bioLinks->isNotEmpty())
            <div class="mt-7 mb-14">
                <div class="space-y-3">
                    @foreach($team->bioLinks as $link)
                        <x-ts-button
                            variant="primary"
                            :color="$team->brand_color ?? null"
                            href="{{ $link->url . (str_contains($link->url, '?') ? '&utm_source=picstome' : '?utm_source=picstome') }}"
                            target="_blank"
                            rel="noopener noreferrer nofollow"
                            class="w-full text-base!"
                        ><span class="truncate">{{ $link->title }}</span></x-ts-button>
                    @endforeach
                </div>
            </div>
         @endif

        @include('partials.social-links')

        @unlesssubscribed($team)
            <div class="py-3">
                @include('partials.powered-by')
            </div>
        @endsubscribed
    </div>

    @if($team->hasCompletedOnboarding() && $team->show_pay_button && $paymentsAvailable)
        <x-ts-modal
            name="generate-payment-link"
            x-init="if (new URL(window.location.href).searchParams.get('pay') === '1') { $nextTick(() => $tsui.open.modal('generate-payment-link')) }"
            class="w-full sm:max-w-lg text-left"
        >
            <form wire:submit="checkout" class="space-y-6">
                <div>
                    <x-ts-heading size="lg">{{ __('Send a Payment to :team', ['team' => $team->name]) }}</x-ts-heading>
                    <x-ts-subheading>{{ __('Enter the amount and a note for your payment. You’ll be redirected to a secure checkout.') }}</x-ts-subheading>
                </div>
                <x-ts-input wire:model="amount" type="number" :label="__('Amount (in :currency)', ['currency' => strtoupper($team->stripe_currency ?? 'EUR')])" required />
                <x-ts-input wire:model="description" :label="__('Note or Description')" type="text" required />
                <div class="flex">
                    <x-ts-spacer />
                    <x-ts-button type="submit" variant="primary">{{ __('Continue to Payment') }}</x-ts-button>
                </div>
            </form>
        </x-ts-modal>
    @endif
</div>
