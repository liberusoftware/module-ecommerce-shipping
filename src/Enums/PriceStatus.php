<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Enums;

/** Offered prices expire and may be swept. A selected price is evidence and is never swept. */
enum PriceStatus: string
{
    case Offered = 'offered';
    case Selected = 'selected';
}
