<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Data;

use InvalidArgumentException;
use Liberu\Ecommerce\Shipping\Enums\TransitBasis;

/**
 * An integer transit-day range and what those days are.
 *
 * Never prose, never a date. "3-5 days", "next week" and "Tues" are not
 * estimates: they cannot be compared, sorted, or resolved by a caller.
 */
final readonly class TransitEstimate
{
    public function __construct(
        public int $minDays,
        public int $maxDays,
        public TransitBasis $basis,
    ) {
        if ($minDays < 0 || $maxDays < $minDays) {
            throw new InvalidArgumentException("A transit estimate of {$minDays}-{$maxDays} days is not a range.");
        }
    }

    public function describe(): string
    {
        $unit = $this->basis === TransitBasis::BusinessDays ? 'business days' : 'calendar days';

        return $this->minDays === $this->maxDays
            ? "{$this->minDays} {$unit}"
            : "{$this->minDays}-{$this->maxDays} {$unit}";
    }
}
