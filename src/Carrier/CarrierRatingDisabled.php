<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Carrier;

/**
 * No carrier implementation is bound.
 *
 * This is a configuration, not a fault: a store that prices from its own rate
 * tables is a supported, common deployment. Surfaces say so plainly and show no
 * error.
 */
final readonly class CarrierRatingDisabled implements CarrierRatingOutcome
{
    public function code(): string
    {
        return 'carrier_rating_disabled';
    }
}
