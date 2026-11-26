<?php

namespace App\Enums;

/**
 * Lifecycle of a booking.
 *
 * Only the pending states and a confirmed booking block a slot. Everything
 * else is history and must not make the calendar look busy.
 */
enum BookingStatus: string
{
    case PendingPayment = 'pending_payment';
    case PendingBizum = 'pending_bizum';
    case Confirmed = 'confirmed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case NoShow = 'no_show';

    /**
     * Whether a booking in this state reserves the slot.
     */
    public function blocksAvailability(): bool
    {
        return match ($this) {
            self::PendingPayment, self::PendingBizum, self::Confirmed => true,
            self::Expired, self::Cancelled, self::Completed, self::NoShow => false,
        };
    }

    public function isPending(): bool
    {
        return in_array($this, [self::PendingPayment, self::PendingBizum], true);
    }

    public function isFinal(): bool
    {
        return ! $this->blocksAvailability();
    }

    /**
     * Human label used by the calendars and emails.
     */
    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => __('Pending payment'),
            self::PendingBizum => __('Pending Bizum'),
            self::Confirmed => __('Confirmed'),
            self::Expired => __('Expired'),
            self::Cancelled => __('Cancelled'),
            self::Completed => __('Completed'),
            self::NoShow => __('No show'),
        };
    }

    /**
     * TallStackUI badge colour for the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::PendingPayment, self::PendingBizum => 'amber',
            self::Confirmed => 'green',
            self::Completed => 'blue',
            self::NoShow => 'red',
            self::Expired, self::Cancelled => 'zinc',
        };
    }
}
