<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Carrier;

/**
 * A carrier answered, and its answer was that it has nothing for this
 * destination. A working carrier saying no is not a carrier being down.
 */
final readonly class CarrierDoesNotServeDestination implements CarrierRatingOutcome
{
    public function __construct(public string $carrierCode) {}

    public function code(): string
    {
        return 'carrier_does_not_serve_destination';
    }
}
