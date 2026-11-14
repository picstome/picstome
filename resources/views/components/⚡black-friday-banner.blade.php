<?php

use App\Services\BlackFridayPromotion;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public bool $visible = false;

    public ?string $offer = null;

    public string $title = '';

    public string $description = '';

    public string $ctaLabel = '';

    public string $ctaUrl = '';

    public function mount(bool $forceVisible = false): void
    {
        if (session('black_friday_banner_dismissed')) {
            return;
        }

        if (! $forceVisible && ! request()->routeIs('dashboard')) {
            return;
        }

        if (request()->routeIs('subscribe*', 'black-friday*')) {
            return;
        }

        $team = Auth::user()?->currentTeam;
        $promotion = app(BlackFridayPromotion::class);
        $offer = $promotion->offerFor($team);

        if (! in_array($offer, ['free', 'monthly'], true)) {
            return;
        }

        if ($team?->subscription('default')?->stripe_schedule_id) {
            return;
        }

        $this->visible = true;
        $this->offer = $offer;
        $this->ctaUrl = route('subscribe');

        if ($offer === 'monthly') {
            $this->title = __('Black Friday: switch to annual and save 20%');
            $this->description = __('Pay nothing today. The change is applied at your next renewal.');
            $this->ctaLabel = __('See annual offer');
        } else {
            $this->title = __('Black Friday: your first month from 1 €');
            $this->description = __('100 GB and 250 GB for 1 €, 1 TB for 5 €, or 20% off annual and one-time. The coupon is applied automatically.');
            $this->ctaLabel = __('See offers');
        }
    }

    public function dismiss(): void
    {
        session()->put('black_friday_banner_dismissed', true);

        $this->visible = false;
    }
}; ?>

<div>
    @if ($visible)
        <div class="mx-auto w-full max-w-7xl px-4 pt-4 lg:px-8">
            <section
                class="border-brand-green/20 bg-brand-green text-brand-cream dark:bg-brand-green-deep relative overflow-hidden rounded-2xl border p-4 shadow-sm sm:p-5 dark:border-white/10"
                aria-label="{{ __('Black Friday promotion') }}"
                data-test="black-friday-banner"
            >
                <div
                    class="pointer-events-none absolute -top-16 -right-10 size-40 rounded-full bg-white/10 blur-2xl"
                ></div>

                <div class="relative flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white/15">
                            <x-icon name="gift" class="size-5" />
                        </span>

                        <div class="min-w-0">
                            <p class="text-sm font-semibold sm:text-base">{{ $title }}</p>
                            <p class="text-brand-cream/75 mt-0.5 text-xs leading-relaxed sm:text-sm">
                                {{ $description }}
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2 sm:ps-4">
                        <x-ts-button :href="$ctaUrl" variant="primary" size="sm" class="btn-coral border-0">
                            {{ $ctaLabel }}
                        </x-ts-button>

                        <button
                            type="button"
                            wire:click="dismiss"
                            class="text-brand-cream/60 hover:text-brand-cream rounded-lg p-1.5 transition hover:bg-white/10"
                            aria-label="{{ __('Dismiss') }}"
                            data-test="black-friday-banner-dismiss"
                        >
                            <x-icon name="x-mark" class="size-4" />
                        </button>
                    </div>
                </div>
            </section>
        </div>
    @endif
</div>
