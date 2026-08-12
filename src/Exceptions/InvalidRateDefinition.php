<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Exceptions;

final class InvalidRateDefinition extends ShippingException
{
    public static function flatRateNeedsAmount(): self
    {
        return new self('A flat rate must declare an amount in minor units.');
    }

    public static function tableRateNeedsAxis(): self
    {
        return new self('A table rate must declare the axis its bands are looked up on.');
    }

    public static function flatRateTakesNoBands(): self
    {
        return new self('A flat rate declares no bands. Declare it as a table rate, or drop the bands.');
    }

    public static function negativeThreshold(string $field): self
    {
        return new self("[{$field}] must not be negative.");
    }
}
