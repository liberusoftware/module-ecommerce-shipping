<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Data;

use Liberu\Ecommerce\Shipping\Enums\AppliedRule;
use Liberu\Ecommerce\Shipping\Enums\PriceKind;

/**
 * A priced option a buyer may choose, and the recorded price backing it.
 *
 * `reference` is what a caller presents to select it: no surface ever sends a
 * price, and the server prices the request that acts.
 */
final readonly class ShippingOption
{
    public function __construct(
        public string $reference,
        public string $serviceLevelCode,
        public string $serviceLevelName,
        public Money $amount,
        public ?TransitEstimate $estimate,
        public PriceKind $kind,
        public AppliedRule $appliedRule,
        public ?string $carrierCode = null,
    ) {}

    public function isFree(): bool
    {
        return $this->amount->isZero();
    }
}
