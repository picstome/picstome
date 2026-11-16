<?php

namespace App\Livewire\Concerns;

use App\Exceptions\InsufficientStorageException;
use App\Models\Team;
use App\Services\AttributionService;
use App\Services\BlackFridayPromotion;
use App\Services\PlanCatalog;
use App\Services\PlanEligibilityService;
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

    /**
     * Query string of the page load that opened the pricing page, minus the
     * parameters owned by the pricing UI. Appended to the login/register
     * return URLs so GA4 and Tag Manager keep the campaign and attribution
     * signals.
     *
     * @var array<string, string>
     */
    public array $returnQuery = [];

    public ?string $upgradeTier = null;

    public ?string $upgradeDate = null;

    public string $statusMessage = '';

    public string $statusType = 'success';

    /**
     * Purchase payload for the GA4 event fired on the success page.
     *
     * @var array<string, mixed>|null
     */
    public ?array $purchase = null;

    public function mountPricingPage(): void
    {
        app(AttributionService::class)->remember(request());

        $this->returnQuery = $this->trackingQuery();

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
            $this->purchase = $this->purchaseFromCheckout();
        }

        if (request()->string('checkout')->toString() === 'cancelled') {
            $this->statusMessage = __('The payment was cancelled. You can try again whenever you want.');
            $this->statusType = 'warning';
        }
    }

    /**
     * Build a relative /subscribe URL that keeps the tracking parameters of
     * the current visit and overrides the plan selection.
     *
     * @param  array<string, string>  $overrides
     */
    public function subscribeUrl(array $overrides = []): string
    {
        return route('subscribe', array_merge($this->returnQuery, $overrides), absolute: false);
    }

    /**
     * Query parameters worth carrying through the auth redirects. Plan
     * selection and checkout state are owned by this page, so they are
     * rebuilt instead of replayed.
     *
     * @return array<string, string>
     */
    private function trackingQuery(): array
    {
        return collect(request()->query())
            ->except(['interval', 'checkout', 'session_id'])
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->all();
    }

    public function team(): ?Team
    {
        return Auth::user()?->currentTeam;
    }

    /**
     * Verify the Stripe session before telling GA4 that a purchase happened.
     * Anyone can open the success URL; only the real session may count.
     *
     * @return array<string, mixed>|null
     */
    private function purchaseFromCheckout(): ?array
    {
        $team = $this->team();
        $sessionId = request()->string('session_id')->toString();

        if (! $team || $sessionId === '') {
            return null;
        }

        return app(SubscriptionCheckoutService::class)->receipt($team, $sessionId);
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
        $team = $this->team();
        $eligibility = app(PlanEligibilityService::class);
        $usedBytes = $team ? $eligibility->usedBytes($team) : 0;

        return collect($this->catalog()->displayTiers())
            ->map(function (array $plan) use ($team, $eligibility, $usedBytes): array {
                $plan['fits'] = $team === null || $eligibility->fitsBytes($usedBytes, $plan['key']);
                $plan['storage_message'] = $plan['fits']
                    ? null
                    : $eligibility->message($usedBytes, $plan['key']);

                return $plan;
            })
            ->all();
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

        $eligibility = app(PlanEligibilityService::class);
        $usedBytes = $eligibility->usedBytes($team);

        if (! $eligibility->fitsBytes($usedBytes, $tier)) {
            $this->toast()->error($eligibility->message($usedBytes, $tier))->send();

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
        } catch (InsufficientStorageException $exception) {
            $this->toast()->error($exception->getMessage())->send();
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
        $this->trackBeginCheckout($tier, $interval);

        $team = $this->team();

        if (! $team) {
            return redirect()->route('register', [
                'redirect' => $this->subscribeUrl(['plan' => $tier, 'interval' => $interval]),
            ]);
        }

        try {
            app(PlanEligibilityService::class)->assertFits($team, $tier);
        } catch (InsufficientStorageException $exception) {
            $this->statusMessage = $exception->getMessage();
            $this->statusType = 'warning';
            $this->toast()->error($exception->getMessage())->send();

            return null;
        }

        if ($this->state() === 'monthly' && $interval === 'annual') {
            $this->openUpgrade($tier);

            return null;
        }

        try {
            $checkout = app(SubscriptionCheckoutService::class)->checkout(
                $team,
                $tier,
                $interval,
                app(AttributionService::class)->capture(request()),
            );

            return redirect($checkout->url);
        } catch (Throwable $exception) {
            report($exception);
            $message = $exception instanceof InsufficientStorageException
                ? $exception->getMessage()
                : __('We could not start the checkout. Please try again.');
            $this->statusMessage = $message;
            $this->statusType = $exception instanceof InsufficientStorageException ? 'warning' : 'error';
            $this->toast()->error($message)->send();

            return null;
        }
    }

    public function checkoutLifetime(string $tier): mixed
    {
        $this->trackBeginCheckout($tier, 'lifetime');

        $team = $this->team();

        if (! $team) {
            return redirect()->route('register', [
                'redirect' => $this->subscribeUrl(['plan' => $tier]),
            ]);
        }

        try {
            app(PlanEligibilityService::class)->assertFits($team, $tier);
        } catch (InsufficientStorageException $exception) {
            $this->statusMessage = $exception->getMessage();
            $this->statusType = 'warning';
            $this->toast()->error($exception->getMessage())->send();

            return null;
        }

        try {
            $checkout = app(SubscriptionCheckoutService::class)->checkoutLifetime(
                $team,
                $tier,
                app(AttributionService::class)->capture(request()),
            );

            return redirect($checkout->url);
        } catch (Throwable $exception) {
            report($exception);
            $message = $exception instanceof InsufficientStorageException
                ? $exception->getMessage()
                : __('We could not start the checkout. Please try again.');
            $this->statusMessage = $message;
            $this->statusType = $exception instanceof InsufficientStorageException ? 'warning' : 'error';
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

    /**
     * Fire begin_checkout before the Stripe redirect. Served from the Livewire
     * action instead of an Alpine attribute so the pricing markup stays clean.
     */
    private function trackBeginCheckout(string $tier, string $interval): void
    {
        $price = ((int) config("plans.tiers.{$tier}.prices.{$interval}", 0)) / 100;

        $this->dispatch('analytics:event', name: 'begin_checkout', params: [
            'currency' => (string) config('plans.currency', 'EUR'),
            'value' => $price,
            'items' => [[
                'item_id' => $tier,
                'item_name' => (string) config("plans.tiers.{$tier}.name", $tier),
                'item_variant' => $interval,
                'price' => $price,
                'quantity' => 1,
            ]],
        ]);
    }

    private function formatDate(?Carbon $date): ?string
    {
        return $date
            ?->copy()
            ->timezone(config('promotions.black_friday.timezone', config('app.timezone', 'UTC')))
            ->isoFormat('LL');
    }
}
