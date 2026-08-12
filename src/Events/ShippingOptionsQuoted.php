<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Events;

use Liberu\Ecommerce\Shipping\Data\Destination;
use Liberu\Ecommerce\Shipping\Data\ShippingOptions;

/** Options were priced for a destination. Carries the outcome, not just a count. */
final readonly class ShippingOptionsQuoted
{
    public function __construct(
        public string $tenantId,
        public Destination $destination,
        public ShippingOptions $options,
    ) {}
}
