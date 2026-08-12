<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Exceptions;

/** Expiry refuses. It never silently re-quotes at a price the buyer did not agree to. */
final class ShippingPriceExpired extends ShippingException
{
    private function __construct(string $message, public readonly string $reference)
    {
        parent::__construct($message);
    }

    public static function at(string $reference, string $expiredAt): self
    {
        return new self("Shipping price [{$reference}] expired at {$expiredAt}. Ask for options again and let the buyer choose at the new price.", $reference);
    }
}
