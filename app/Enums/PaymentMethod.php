<?php

namespace App\Enums;

/**
 * How the client paid a booking.
 */
enum PaymentMethod: string
{
    case Card = 'card';
    case Bizum = 'bizum';
    case Cash = 'cash';
    case Transfer = 'transfer';
}
