<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Queries;

use Liberu\Ecommerce\Shipping\Data\Money;

/**
 * What a shipment is charged: a fold over one price line and its adjustment
 * lines.
 *
 * There is no `shipping_total` column, so there is nothing for this to disagree
 * with, and replaying the lines in any order gives the same integer.
 */
final class TotalShippingCharge
{
    public function __construct(private GetShippingPrice $price) {}

    public function __invoke(string $tenantId, string $reference): Money
    {
        return ($this->price)($tenantId, $reference)->total();
    }
}
