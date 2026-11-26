<?php

namespace App\Enums;

/**
 * Natural light preference of a product.
 *
 * It only changes which slots are highlighted as recommended; it never
 * restricts availability, because the client may still book any free time.
 */
enum LightMode: string
{
    case None = 'none';
    case Sunset = 'sunset';
    case Sunrise = 'sunrise';
}
