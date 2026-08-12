<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Exceptions;

/** A price that priced something is evidence: it is never edited and never pruned. */
final class ShippingPriceImmutable extends ShippingException
{
    public static function selected(string $reference): self
    {
        return new self("Shipping price [{$reference}] is selected and immutable. Record an adjustment line instead of changing it.");
    }
}
