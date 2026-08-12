<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Events;

use Liberu\Ecommerce\Shipping\Enums\PriceKind;

/** A price was chosen. From here it is evidence: immutable, and never swept. */
final readonly class ShippingPriceSelected
{
    public function __construct(
        public string $tenantId,
        public string $reference,
        public PriceKind $kind,
        public int $amountMinor,
        public string $currency,
    ) {}
}
