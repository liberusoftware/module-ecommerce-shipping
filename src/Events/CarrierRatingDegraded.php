<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Events;

use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingUnavailable;

/**
 * A bound carrier failed and the buyer was served derived rates instead.
 *
 * Emitted only for a failure. Live rating being switched off is a configuration
 * and is silent, which is the distinction the host could not make.
 */
final readonly class CarrierRatingDegraded
{
    public function __construct(
        public string $tenantId,
        public CarrierRatingUnavailable $outcome,
    ) {}
}
