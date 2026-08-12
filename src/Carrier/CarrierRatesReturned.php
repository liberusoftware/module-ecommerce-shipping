<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Carrier;

use InvalidArgumentException;
use Liberu\Ecommerce\Shipping\Data\CarrierRate;

/** A carrier answered with at least one rate. Never constructible empty. */
final readonly class CarrierRatesReturned implements CarrierRatingOutcome
{
    /** @var list<CarrierRate> */
    public array $rates;

    /** @param  list<CarrierRate>  $rates */
    public function __construct(array $rates)
    {
        if ($rates === []) {
            throw new InvalidArgumentException('A carrier answering with nothing is CarrierDoesNotServeDestination, not an empty rate list.');
        }

        $this->rates = array_values($rates);
    }

    public function code(): string
    {
        return 'carrier_rates_returned';
    }
}
