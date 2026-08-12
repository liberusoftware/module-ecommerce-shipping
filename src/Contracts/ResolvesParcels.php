<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Contracts;

use Liberu\Ecommerce\Shipping\Data\Parcel;
use Liberu\Ecommerce\Shipping\Data\ParcelSet;

/**
 * How this module is told what it is pricing. Declared here, bound by the host.
 *
 * **There is no default binding and leaving it unbound is a fault**, not a
 * configuration: shipping must never look a product weight up, because the
 * product and its weight belong to ecommerce-catalog and importing it would
 * cross the boundary this package exists to hold.
 *
 * Returning null means "this basket resolved to no parcels", which is a
 * different answer from nothing being bound at all. An implementation that
 * cannot find a weight must not invent one — construct parcels through
 * {@see Parcel::fromNullableWeight()} and let
 * the refusal surface.
 */
interface ResolvesParcels
{
    public function resolve(string $tenantId, string $basketReference): ?ParcelSet;
}
