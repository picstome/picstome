<?php

use App\Models\BookingSetting;
use App\Models\Team;
use App\Services\Booking\BookingPageGate;
use Illuminate\View\View;
use Laravel\Cashier\Cashier;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.guest')]
class extends Component
{
    public Team $team;

    public function mount(string $handle): void
    {
        $this->team = Team::where('handle', strtolower($handle))->firstOrFail();

        abort_unless(app(BookingPageGate::class)->canBook($this->team), 404);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product>
     */
    #[Computed]
    public function products()
    {
        return $this->team->products()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function settings(): BookingSetting
    {
        return BookingSetting::forTeam($this->team);
    }

    public function formatAmount(int $minorUnits, string $currency): string
    {
        return Cashier::formatAmount($minorUnits, $currency);
    }

    public function rendering(View $view): void
    {
        $view->title(__('Book a session with :team', ['team' => $this->team->name]));
    }
}; ?>

<div class="mx-auto w-full max-w-3xl px-4 py-10">
    <div class="mb-8 text-center">
        @if ($team->brand_logo_icon_url)
            <img src="{{ $team->brand_logo_icon_url.'&w=192&h=192' }}" class="mx-auto mb-4 size-20 rounded" alt="{{ $team->name }}" />
        @else
            <x-ts-heading size="xl" class="mb-2">{{ $team->name }}</x-ts-heading>
        @endif

        <x-ts-heading level="1" size="xl">{{ __('Book a session') }}</x-ts-heading>
        <x-ts-subheading class="mt-1">{{ __('Choose the session you want and pick a day and time.') }}</x-ts-subheading>
    </div>

    @if ($this->products->isEmpty())
        <div class="rounded-xl border border-zinc-200 bg-white p-8 text-center dark:border-white/10 dark:bg-white/5">
            <x-ts-text variant="subtle">{{ __('No sessions are available right now.') }}</x-ts-text>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($this->products as $product)
                @php($deposit = $product->depositAmount($this->settings))

                <a
                    href="{{ route('handle.book', ['handle' => $team->handle, 'productUlid' => $product->ulid]) }}"
                    wire:navigate
                    class="hover:border-brand-green flex items-start justify-between gap-4 rounded-xl border border-zinc-200 bg-white p-5 text-left transition hover:bg-brand-paper dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10"
                >
                    <span class="min-w-0">
                        <span class="block text-base font-semibold">{{ $product->name }}</span>

                        @if ($product->description)
                            <span class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">{{ $product->description }}</span>
                        @endif

                        <span class="mt-2 block text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $product->duration_minutes }} {{ __('min') }}
                            @if ($product->location_name)
                                · {{ $product->location_name }}
                            @endif
                            @if ($deposit > 0)
                                · {{ __('Deposit of :amount', ['amount' => $this->formatAmount($deposit, $product->currency)]) }}
                            @endif
                        </span>
                    </span>

                    <span class="flex shrink-0 items-center gap-2">
                        <span class="text-base font-semibold">{{ $this->formatAmount($product->price, $product->currency) }}</span>
                        <x-icon name="arrow-right" class="size-4 text-zinc-400" />
                    </span>
                </a>
            @endforeach
        </div>
    @endif

    <div class="mt-8 text-center">
        <a href="{{ route('handle.show', ['handle' => $team->handle]) }}" wire:navigate class="text-sm text-zinc-500 underline hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
            {{ __('Back to :team', ['team' => $team->name]) }}
        </a>
    </div>
</div>
