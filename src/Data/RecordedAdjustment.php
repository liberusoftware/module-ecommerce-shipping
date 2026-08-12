<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Data;

/** One surcharge, handling fee or discount line, with its own reason. */
final readonly class RecordedAdjustment
{
    public function __construct(
        public int $id,
        public Money $amount,
        public string $reasonCode,
        public string $reason,
        public ?int $basisPoints = null,
    ) {}
}
