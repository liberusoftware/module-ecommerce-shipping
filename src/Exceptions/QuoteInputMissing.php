<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Exceptions;

/**
 * A rule needed a number the caller did not supply.
 *
 * Refused rather than defaulted: a subtotal treated as zero silently applies the
 * top band of a price table, and a weight treated as zero prices a lighter box
 * than exists.
 */
final class QuoteInputMissing extends ShippingException
{
    public static function subtotal(string $rule): self
    {
        return new self("[{$rule}] needs the order subtotal in minor units, and none was supplied. A missing subtotal is not zero.");
    }

    public static function itemCount(): self
    {
        return new self('A table rate banded on item count needs the item count, and none was supplied.');
    }
}
