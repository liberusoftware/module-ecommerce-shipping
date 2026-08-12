<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Data;

use Liberu\Ecommerce\Shipping\Enums\PriceKind;

/**
 * Which carrier said what, when, about which parcel and which destination.
 *
 * This is the whole record. Nothing here recomputes the number, because nothing
 * can: ask the carrier again in a minute and the answer may differ.
 */
final readonly class QuotedProvenance implements PriceProvenance
{
    public function __construct(
        public string $carrierCode,
        public string $carrierServiceCode,
        public ?string $carrierRateReference,
        public string $quotedAt,
    ) {}

    public function kind(): PriceKind
    {
        return PriceKind::Quoted;
    }

    public function describe(): string
    {
        return "carrier {$this->carrierCode}, service {$this->carrierServiceCode}, quoted at {$this->quotedAt}";
    }
}
