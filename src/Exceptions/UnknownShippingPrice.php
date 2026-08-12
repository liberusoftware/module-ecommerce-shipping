<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Exceptions;

final class UnknownShippingPrice extends ShippingException
{
    public static function reference(string $reference): self
    {
        return new self("No shipping price [{$reference}] exists for this tenant.");
    }
}
