<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Carrier;

use Throwable;

/**
 * A carrier is bound, and it threw, timed out, or answered with something that
 * was not a rate. Derived options still stand, with an explicit degraded notice.
 */
final readonly class CarrierRatingUnavailable implements CarrierRatingOutcome
{
    public function __construct(
        public string $carrierCode,
        public string $reason,
    ) {}

    public static function from(string $carrierCode, Throwable $exception): self
    {
        return new self($carrierCode, $exception::class.': '.$exception->getMessage());
    }

    public function code(): string
    {
        return 'carrier_unavailable';
    }
}
