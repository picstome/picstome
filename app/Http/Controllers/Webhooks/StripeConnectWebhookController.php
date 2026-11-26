<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\StripeEvent;
use App\Services\Booking\BookingFulfilmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Stripe is the source of truth for booking payments.
 *
 * The success page is only a courtesy: a client can close the tab right after
 * paying, and the booking still has to be confirmed. This endpoint verifies
 * the signature, handles each event once and delegates the actual work to the
 * idempotent fulfilment service.
 *
 * Booking payments are direct charges on the photographer's connected account,
 * so they arrive through the Stripe Connect endpoint and use its own secret.
 * Cashier's platform webhook (`/stripe/webhook`) is a different endpoint and
 * keeps `STRIPE_WEBHOOK_SECRET`; overwriting it silently disabled subscription
 * billing events.
 */
class StripeConnectWebhookController
{
    public function __invoke(Request $request, BookingFulfilmentService $fulfilment): Response
    {
        $secret = (string) config('services.stripe.connect_webhook_secret');

        if ($secret === '') {
            Log::error('Stripe Connect webhook secret is not configured.');

            return response('webhook secret missing', 500);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secret,
            );
        } catch (UnexpectedValueException|SignatureVerificationException $exception) {
            Log::warning('Stripe Connect webhook rejected', ['error' => $exception->getMessage()]);

            return response('invalid signature', 400);
        }

        $account = $event->toArray()['account'] ?? null;

        if (! is_string($account) || $account === '') {
            Log::warning('Stripe Connect webhook without a connected account', ['event' => $event->id]);

            return response('not a connected account event', 400);
        }

        if (StripeEvent::query()->where('stripe_event_id', $event->id)->exists()) {
            return response('ok');
        }

        if ($event->type === 'checkout.session.completed') {
            $object = $event->toArray()['data']['object'] ?? [];

            $booking = Booking::query()
                ->where('ulid', (string) ($object['metadata']['booking_ulid'] ?? ''))
                ->first();

            if ($booking !== null) {
                $kind = PaymentKind::tryFrom((string) ($object['metadata']['payment_kind'] ?? ''))
                    ?? PaymentKind::Full;

                $fulfilment->pay(
                    $booking,
                    $kind,
                    PaymentMethod::Card,
                    $object['payment_intent'] ?? null,
                    $object['customer_details']['email'] ?? null,
                );
            }
        }

        StripeEvent::query()->create([
            'stripe_event_id' => $event->id,
            'type' => $event->type,
            'processed_at' => now(),
        ]);

        return response('ok');
    }
}
