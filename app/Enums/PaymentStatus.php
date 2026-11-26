<?php

namespace App\Enums;

/**
 * Lifecycle of a payment-link payment.
 *
 * Card payments are completed immediately. A Bizum transfer is confirmed by
 * the photographer, so it starts as pending and can later be completed,
 * cancelled or expired.
 */
enum PaymentStatus: string
{
    case PendingBizum = 'pending_bizum';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PendingBizum => __('Pending Bizum'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
            self::Expired => __('Expired'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingBizum => 'amber',
            self::Completed => 'green',
            self::Cancelled, self::Expired => 'zinc',
        };
    }

    public function isPending(): bool
    {
        return $this === self::PendingBizum;
    }
}
