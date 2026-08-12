<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Exceptions;

/** Every domain entry point takes the tenant explicitly; nothing is inferred from a row. */
final class TenantMismatch extends ShippingException
{
    public static function make(string $expected, string $actual): self
    {
        return new self("Record belongs to tenant [{$actual}], not [{$expected}].");
    }
}
