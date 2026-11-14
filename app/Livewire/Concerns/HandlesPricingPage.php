<?php

namespace App\Livewire\Concerns;

use App\Models\Team;
use App\Services\BlackFridayPromotion;
use App\Services\PlanCatalog;
use App\Services\ScheduledPlanChangeService;
use App\Services\SubscriptionCheckoutService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use TallStackUi\Traits\Interactions;
use Throwable;

/**
 * Shared behaviour for the authenticated subscription page and the public
 * Black Friday landing page.
 */
trait HandlesPricingPage
{
    use Interactions;

    public string $interval = 'monthly';

    public ?string $upgradeTier = null;

    public ?string $upgradeDate = null;

    public string $statusMessage = '';

    public string $statusType = 'success';

    public function mountPricingPage(): void
    {
        $offer = $this->promotion()->offerFor($this->team());
        $requestedInterval = request()->string('interval')->toString();

        $this->interval = match (true) {
            $offer === 'monthly' => 'annual',
            in_array($requestedInterval, ['monthly', 'annual', 'lifetime'], true) => $requestedInterval,
            default => 'monthly',
        };

        if (request()->string('checkout')->toString() === 'success') {
            $this->statusMessage = __('Your subscription is being activated. This usually takes a few seconds.');
            $this->statusType = 'success';
        }

        if (request()->string('checkout')->toString() === 'cancelled') {
            $this->statusMessage = __('The payment was cancelled. You can try again whenever you want.');
            $this->statusType = 'warning';
        }
    }

    public function team(): ?Team
    {
        return Auth::user()?->currentTeam;
    }

    public function catalog(): PlanCatalog
    {
        return app(PlanCatalog::class);
    }

    public function promotion(): BlackFridayPromotion
    {
        return app(BlackFridayPromotion::class);
    }

    public function scheduledService(): ScheduledPlanChangeService
    {
        return app(ScheduledPlanChangeService::class);
    }

    public function state(): string
    {
        return $this->promotion()->stateFor($this->team());
    }

    public function offer(): ?string
    {
        return $this->promotion()->offerFor($this->team());
    }

    public function promoActive(): bool
    {
        return $this->promotion()->isActive();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function plans(): array
    {
        return $this->catalog()->displayTiers();
    }

    public function currentTier(): ?string
    {
        return $this->catalog()->tierForStorageLimit($this->team()?->custom_storage_limit);
    }

    public function currentPlanName(): ?string
    {
        return $this->planName($this->currentTier());
    }

    public function planName(?string $tier): ?string
    {
        if (! $tier) {
            return null;
        }

        return config("plans.tiers.{$tier}.name");
    }

    /**
     * @return array{date: Carbon, tier: string|null, price: string|null}|null
     */
    public function scheduledChange(): ?array
    {
        $team = $this->team();

        return $team ? $this->scheduledService()->scheduledChange($team) : null;
    }

    public function scheduledChangeDate(): ?string
    {
        return $this->formatDate($this->scheduledChange()['date'] ?? null);
    }

    public function scheduledChangePlanName(): ?string
    {
        return $this->planName($this->scheduledChange()['tier'] ?? null);
    }

    public function openUpgrade(string $tier): void
    {
        $team = $this->team();

        if (! $team || ! $this->promotion()->canScheduleUpgrade($team, $tier)) {
            $this->toast()->error(__('This upgrade is not available for your subscription.'))->send();

            return;
        }

        $this->upgradeTier = $tier;

        try {
            $this->upgradeDate = $this->formatDate($this->scheduledService()->estimatedChangeAt($team));
        } catch (Throwable $exception) {
            report($exception);
            $this->upgradeDate = null;
        }

        $this->dispatch('modal:upgrade-open');
    }

    public function confirmUpgrade(): void
    {
        $team = $this->team();

        if (! $team || ! $this->upgradeTier) {
            return;
        }

        try {
            $this->scheduledService()->schedule($team, $this->upgradeTier);
            $this->dispatch('modal:upgrade-close');
            $this->toast()->success(__('Your annual upgrade has been scheduled.'))->send();
            $this->upgradeTier = null;
            $this->upgradeDate = null;
        } catch (Throwable $exception) {
            report($exception);
            $this->toast()->error(__('We could not schedule the upgrade. Please try again.'))->send();
        }
    }

    public function cancelScheduledChange(): void
    {
        $team = $this->team();

        if (! $team) {
            return;
        }

        try {
            $this->scheduledService()->cancel($team);
            $this->toast()->success(__('The scheduled annual upgrade has been cancelled.'))->send();
        } catch (Throwable $exception) {
            report($exception);
            $this->toast()->error(__('We could not cancel the scheduled upgrade. Please try again.'))->send();
        }
    }

    public function checkout(string $tier, string $interval): mixed
    {
        $team = $this->team();

        if (! $team) {
            return redirect()->route('register', [
                'redirect' => route('subscribe', ['plan' => $tier, 'interval' => $interval], absolute: false),
            ]);
        }

        if ($this->state() === 'monthly' && $interval === 'annual') {
            $this->openUpgrade($tier);

            return null;
        }

        try {
            $checkout = app(SubscriptionCheckoutService::class)->checkout($team, $tier, $interval);

            return redirect($checkout->url);
        } catch (Throwable $exception) {
            report($exception);
            $message = __('We could not start the checkout. Please try again.');
            $this->statusMessage = $message;
            $this->statusType = 'error';
            $this->toast()->error($message)->send();

            return null;
        }
    }

    public function checkoutLifetime(string $tier): mixed
    {
        $team = $this->team();

        if (! $team) {
            return redirect()->route('register', [
                'redirect' => route('subscribe', ['plan' => $tier], absolute: false),
            ]);
        }

        try {
            $checkout = app(SubscriptionCheckoutService::class)->checkoutLifetime($team, $tier);

            return redirect($checkout->url);
        } catch (Throwable $exception) {
            report($exception);
            $message = __('We could not start the checkout. Please try again.');
            $this->statusMessage = $message;
            $this->statusType = 'error';
            $this->toast()->error($message)->send();

            return null;
        }
    }

    public function upgradePrice(): ?string
    {
        if (! $this->upgradeTier) {
            return null;
        }

        return $this->catalog()->formatCents(
            $this->catalog()->annualPriceAfterDiscountCents($this->upgradeTier)
        );
    }

    public function upgradePlanName(): ?string
    {
        return $this->planName($this->upgradeTier);
    }

    public function promoEndsLabel(): string
    {
        return $this->promotion()->offerEndsAtLabel();
    }

    public function manageBillingUrl(): string
    {
        return route('billing-portal');
    }

    private function formatDate(?Carbon $date): ?string
    {
        return $date
            ?->copy()
            ->timezone(config('promotions.black_friday.timezone', config('app.timezone', 'UTC')))
            ->isoFormat('LL');
    }
}
