<?php

namespace App\Enums;

/**
 * Which part of the booking total a payment settles.
 *
 * A full-payment booking produces one `Full` payment; a deposit booking
 * produces a `Deposit` and later a `Balance` payment, each with its own
 * invoice.
 */
enum PaymentKind: string
{
    case Full = 'full';
    case Deposit = 'deposit';
    case Balance = 'balance';
}
