<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Exceptions;

/** Bound, asked, and answered with nothing — distinct from nothing being bound at all. */
final class ParcelsNotResolved extends ShippingException
{
    public static function for(string $basketReference): self
    {
        return new self("No parcels were resolved for [{$basketReference}].");
    }
}
