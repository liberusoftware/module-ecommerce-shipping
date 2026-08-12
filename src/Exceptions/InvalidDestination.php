<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Exceptions;

final class InvalidDestination extends ShippingException
{
    public static function country(string $given): self
    {
        return new self("[{$given}] is not a two-letter ISO 3166-1 alpha-2 country code.");
    }
}
