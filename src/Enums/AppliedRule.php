<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Enums;

/** Which rule produced a price, recorded so a derived price can be reproduced. */
enum AppliedRule: string
{
    case Flat = 'flat';
    case TableBand = 'table_band';
    case FreeThreshold = 'free_threshold';
    case CarrierQuote = 'carrier_quote';
}
