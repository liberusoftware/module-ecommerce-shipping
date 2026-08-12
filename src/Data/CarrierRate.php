<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Data;

/**
 * One answer from a carrier, as given.
 *
 * `rateReference` is the carrier's own identifier for the answer. It is stored
 * as provenance and is never shown to a shopper.
 */
final readonly class CarrierRate
{
    public function __construct(
        public string $carrierCode,
        public string $serviceCode,
        public string $serviceName,
        public Money $amount,
        public ?TransitEstimate $estimate = null,
        public ?string $rateReference = null,
    ) {}
}
