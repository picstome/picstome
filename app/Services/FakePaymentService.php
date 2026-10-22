<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Team;

/**
 * Creates payments without touching Stripe.
 *
 * Used by the local "fake payments" mode (PAYMENTS_FAKE=true) so the payment
 * flow, the invoices and the notifications can be tested without a connected
 * account. Payments created here are marked with a `fake_` intent id.
 */
class FakePaymentService
{
    /**
     * Whether the fake payments mode is active.
     *
     * The config flag is already restricted to the local environment, this
     * is the runtime guard so a misconfigured production server can never
     * simulate payments.
     */
    public static function enabled(): bool
    {
        return (bool) config('picstome.payments_fake')
            && ! app()->environment('production')
            && (bool) config('app.debug');
    }

    /**
     * Create a paid payment for the team.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createPayment(Team $team, int $amount, string $description, array $attributes = []): Payment
    {
        $email = $attributes['email'] ?? null;

        if ($email) {
            $this->findOrCreateCustomer($team, $email);
        }

        return $team->payments()->create([
            'amount' => $amount,
            'currency' => $team->stripe_currency ?: 'eur',
            'description' => $description,
            'customer_email' => $email,
            'stripe_payment_intent_id' => 'fake_'.uniqid(),
            'completed_at' => now(),
            ...collect($attributes)->except('email')->all(),
        ]);
    }

    /**
     * Find the customer by email or create it.
     */
    protected function findOrCreateCustomer(Team $team, string $email): Customer
    {
        return $team->customers()->firstOrCreate(
            ['email' => $email],
            ['name' => $email]
        );
    }
}
