<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\View\View;
use Laravel\Cashier\Cashier;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('layouts.guest')]
class extends Component
{
    public Team $team;

    public Payment $payment;

    public function mount(string $handle, string $reference): void
    {
        $this->team = Team::where('handle', strtolower($handle))->firstOrFail();

        $this->payment = $this->team->payments()
            ->where('reference', $reference)
            ->where('payment_method', 'bizum')
            ->firstOrFail();

        if ($this->payment->isPendingBizum() && $this->payment->expires_at?->isPast()) {
            $this->payment->update(['status' => PaymentStatus::Expired]);
        }
    }

    public function rendering(View $view): void
    {
        $view->title(__('Pay with Bizum').' - '.$this->team->name);
    }

    public function formattedAmount(): string
    {
        return Cashier::formatAmount($this->payment->amount, $this->payment->currency);
    }

    public function sessionLabel(): ?string
    {
        $metadata = $this->payment->metadata ?? [];

        if (blank($metadata['booking_date'] ?? null)) {
            return null;
        }

        $timezone = (string) ($metadata['timezone'] ?? 'UTC');
        $date = Carbon::parse((string) $metadata['booking_date'], $timezone);
        $time = collect([
            $metadata['booking_start_time'] ?? null,
            $metadata['booking_end_time'] ?? null,
        ])->filter()->implode(' - ');

        return $date->isoFormat('LL').($time !== '' ? ' · '.$time : '');
    }
} ?>

<div class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="mx-auto w-full max-w-xl space-y-7">
        <div class="text-center">
            <p class="text-sm font-medium tracking-wide text-zinc-500 uppercase dark:text-zinc-400">
                {{ $this->team->name }}
            </p>

            <x-ts-heading size="xl" level="1" class="mt-2">
                {{ $this->payment->description }}
            </x-ts-heading>

            @if ($this->sessionLabel())
                <p class="mt-1 text-base font-medium text-zinc-600 dark:text-zinc-300">
                    {{ $this->sessionLabel() }}
                </p>
            @endif

            <p class="text-brand-ink dark:text-brand-cream mt-4 text-4xl font-black tabular-nums">
                {{ $this->formattedAmount() }}
            </p>
        </div>

        @if ($this->payment->isCompleted())
            <x-ts-callout icon="check-circle" variant="success">
                <x-ts-callout-heading>{{ __('Payment confirmed') }}</x-ts-callout-heading>
                <x-ts-callout-text>
                    {{ __('We have received your payment. Thank you!') }}
                </x-ts-callout-text>
            </x-ts-callout>
        @elseif ($this->payment->isPendingBizum())
            <div class="space-y-7 rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs sm:p-8 dark:border-white/10 dark:bg-white/5">
                <div>
                    <p class="text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">
                        {{ __('Send the Bizum to') }}
                    </p>
                    <div class="mt-1 flex items-center justify-between gap-3">
                        <p class="text-brand-ink dark:text-brand-cream text-4xl font-black tracking-tight sm:text-5xl">
                            {{ $this->team->bizum_phone }}
                        </p>

                        <button
                            type="button"
                            x-data="{ copied: false }"
                            x-on:click="navigator.clipboard.writeText(@js($this->team->bizum_phone)); copied = true; setTimeout(() => copied = false, 1500)"
                            class="rounded-lg p-2 text-zinc-500 transition hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-white/10"
                            :aria-label="__('Copy phone')"
                        >
                            <x-icon name="clipboard-document" class="size-5" x-show="!copied" />
                            <x-icon name="check" class="size-5 text-emerald-500" x-show="copied" x-cloak />
                        </button>
                    </div>
                </div>

                <div class="border-t border-zinc-100 pt-6 dark:border-white/10">
                    <p class="text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">
                        {{ __('Reference') }}
                    </p>
                    <div class="mt-1 flex items-center justify-between gap-3">
                        <p class="text-brand-ink dark:text-brand-cream text-4xl font-black tracking-[0.15em]">
                            {{ $this->payment->reference }}
                        </p>

                        <button
                            type="button"
                            x-data="{ copied: false }"
                            x-on:click="navigator.clipboard.writeText(@js($this->payment->reference)); copied = true; setTimeout(() => copied = false, 1500)"
                            class="rounded-lg p-2 text-zinc-500 transition hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-white/10"
                            :aria-label="__('Copy reference')"
                        >
                            <x-icon name="clipboard-document" class="size-5" x-show="!copied" />
                            <x-icon name="check" class="size-5 text-emerald-500" x-show="copied" x-cloak />
                        </button>
                    </div>
                </div>
            </div>

            @if (filled($this->team->bizum_instructions))
                <p class="whitespace-pre-line text-center text-sm text-zinc-600 dark:text-zinc-300">
                    {{ $this->team->bizum_instructions }}
                </p>
            @endif

            <p class="text-center text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('The payment is confirmed as soon as the photographer checks the transfer.') }}
            </p>
        @else
            <x-ts-callout icon="exclamation-circle" variant="warning">
                <x-ts-callout-heading>{{ $this->payment->status->label() }}</x-ts-callout-heading>
                <x-ts-callout-text>
                    {{ __('This payment request is no longer active. Please contact the photographer.') }}
                </x-ts-callout-text>
            </x-ts-callout>
        @endif
    </div>
</div>
