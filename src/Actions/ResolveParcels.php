<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Actions;

use Liberu\Ecommerce\Shipping\Contracts\ResolvesParcels;
use Liberu\Ecommerce\Shipping\Data\Parcel;
use Liberu\Ecommerce\Shipping\Data\ParcelSet;
use Liberu\Ecommerce\Shipping\Exceptions\ParcelResolverNotBound;
use Liberu\Ecommerce\Shipping\Exceptions\ParcelsNotResolved;

/**
 * Asks the host what is being shipped.
 *
 * Three outcomes and three different meanings: nothing bound is a deployment
 * fault, a null answer is a basket that resolved to no parcels, and a parcel
 * with no weight is refused by {@see Parcel}
 * itself rather than quietly treated as weighing nothing.
 */
final class ResolveParcels
{
    public function __construct(private ?ResolvesParcels $resolver = null) {}

    public function __invoke(string $tenantId, string $basketReference): ParcelSet
    {
        if (! $this->resolver instanceof ResolvesParcels) {
            throw ParcelResolverNotBound::make();
        }

        $parcels = $this->resolver->resolve($tenantId, $basketReference);

        if (! $parcels instanceof ParcelSet) {
            throw ParcelsNotResolved::for($basketReference);
        }

        return $parcels;
    }
}
