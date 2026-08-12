<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Exceptions;

use Liberu\Ecommerce\Shipping\Contracts\ResolvesParcels;

/**
 * Unbound is a fault, not a configuration.
 *
 * This module must never look a product weight up — that belongs to
 * ecommerce-catalog, and importing it is a boundary violation. It is told the
 * parcels to price, so a deployment that has bound nothing cannot price at all.
 */
final class ParcelResolverNotBound extends ShippingException
{
    public static function make(): self
    {
        return new self('No ['.ResolvesParcels::class.'] implementation is bound. Shipping is told its parcels; it never looks a weight up.');
    }
}
