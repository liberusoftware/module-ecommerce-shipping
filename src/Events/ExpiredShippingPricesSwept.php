<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Events;

/** Unselected, expired offers were removed. Selected prices are never included. */
final readonly class ExpiredShippingPricesSwept
{
    public function __construct(
        public string $tenantId,
        public int $swept,
    ) {}
}
