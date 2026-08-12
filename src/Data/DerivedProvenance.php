<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Data;

use Liberu\Ecommerce\Shipping\Enums\AppliedRule;
use Liberu\Ecommerce\Shipping\Enums\PriceKind;

/** The rules that produced a derived price, enough to recompute it from them. */
final readonly class DerivedProvenance implements PriceProvenance
{
    public function __construct(
        public int $zoneId,
        public string $zoneCode,
        public int $rateId,
        public AppliedRule $appliedRule,
        public ?int $rateBandId = null,
    ) {}

    public function kind(): PriceKind
    {
        return PriceKind::Derived;
    }

    public function describe(): string
    {
        return "zone {$this->zoneCode}, rate {$this->rateId}, rule {$this->appliedRule->value}";
    }
}
