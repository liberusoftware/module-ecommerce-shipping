<?php

declare(strict_types=1);

use Liberu\Ecommerce\Shipping\Carrier\CarrierRatesReturned;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingOutcome;
use Liberu\Ecommerce\Shipping\Contracts\FetchesCarrierRates;
use Liberu\Ecommerce\Shipping\Data\CarrierRate;
use Liberu\Ecommerce\Shipping\Data\Destination;
use Liberu\Ecommerce\Shipping\Data\Money;
use Liberu\Ecommerce\Shipping\Data\ParcelSet;
use Liberu\Ecommerce\Shipping\Data\TransitEstimate;
use Liberu\Ecommerce\Shipping\Enums\TransitBasis;
use Liberu\Ecommerce\Shipping\Tests\TestCase;

uses(TestCase::class)->in('Unit', 'Feature');

/*
 * Carrier doubles live here rather than in one test file, so every suite that
 * needs one has it however the run is filtered.
 */
function carrierAnswering(CarrierRatingOutcome $outcome): FetchesCarrierRates
{
    return new class($outcome) implements FetchesCarrierRates
    {
        public function __construct(private CarrierRatingOutcome $outcome) {}

        public function fetch(string $tenantId, Destination $destination, ParcelSet $parcels): CarrierRatingOutcome
        {
            return $this->outcome;
        }
    };
}

function carrierThrowing(): FetchesCarrierRates
{
    return new class() implements FetchesCarrierRates
    {
        public function fetch(string $tenantId, Destination $destination, ParcelSet $parcels): CarrierRatingOutcome
        {
            throw new RuntimeException('connect timeout after 15s');
        }
    };
}

function acmeRate(int $minor = 1_234): CarrierRatesReturned
{
    return new CarrierRatesReturned([new CarrierRate(
        'acme',
        'acme_next_day',
        'Acme Next Day',
        new Money($minor, 'GBP'),
        new TransitEstimate(1, 1, TransitBasis::BusinessDays),
        'rate_9f2c',
    )]);
}
