<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Events;

/** A surcharge or reduction was recorded beside a price, never folded into it. */
final readonly class ShippingPriceAdjusted
{
    public function __construct(
        public string $tenantId,
        public string $reference,
        public int $amountMinor,
        public string $currency,
        public string $reasonCode,
    ) {}
}
