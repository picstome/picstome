<?php

use App\Models\Team;
use Facades\App\Services\StripeConnectService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new
#[Layout('layouts.guest')]
class extends Component
{
    public Team $team;

    public ?Collection $galleries;

    public $amount;

    public $description;

    public function mount(string $handle)
    {
        $this->team = Team::where('handle', strtolower($handle))->firstOrFail();

        abort_if(! $this->team, 404);

        $this->galleries = $this->team->galleries()->public()->with('photos')->get();
    }

    public function rendering(View $view): void
    {
        $view->title($this->team->name);
    }

    public function checkout()
    {
        $this->validate([
            'amount' => 'required|integer|min:1',
            'description' => 'required|string|max:255',
        ]);

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
            @if($team->galleries()->public()->exists() && !$team->portfolio_public_disabled)
                <div class="flex justify-center">
                    <flux:button
                        variant="primary"
                        :color="$team->brand_color ?? null"
                        href="{{ route('portfolio.index', ['handle' => $team->handle]) }}"
                        wire:navigate
                    >
                         {{ __('View Portfolio') }}
                    </flux:button>
                </div>
            @endif
        </div>

        @if($team->bioLinks->isNotEmpty())
            <div class="mt-7 mb-14">
                <div class="space-y-3">
                    @foreach($team->bioLinks as $link)
                        <flux:button
                            variant="primary"
                            :color="$team->brand_color ?? null"
                            href="{{ $link->url . (str_contains($link->url, '?') ? '&utm_source=picstome' : '?utm_source=picstome') }}"
                            target="_blank"
                            rel="noopener noreferrer nofollow"
                            class="w-full text-base!"
                        ><span class="truncate">{{ $link->title }}</span></flux:button>
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

    @if($team->hasCompletedOnboarding() && $team->show_pay_button)
        <flux:modal
            name="generate-payment-link"
            x-init="if (new URL(window.location.href).searchParams.get('pay') === '1') { $nextTick(() => $flux.modal('generate-payment-link').show()) }"
            class="w-full sm:max-w-lg text-left"
        >
            <form wire:submit="checkout" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Send a Payment to :team', ['team' => $team->name]) }}</flux:heading>
                    <flux:subheading>{{ __('Enter the amount and a note for your payment. You’ll be redirected to a secure checkout.') }}</flux:subheading>
                </div>
                <flux:input wire:model="amount" type="number" :label="__('Amount (in :currency)', ['currency' => strtoupper($team->stripe_currency ?? 'EUR')])" required />
                <flux:input wire:model="description" :label="__('Note or Description')" type="text" required />
                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">{{ __('Continue to Payment') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
