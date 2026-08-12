<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Data;

/**
 * One band of a table rate, half-open: [lowerBound, upperBound).
 *
 * The top band is unbounded because it says so, not because its upper bound
 * happens to be null.
 */
final readonly class BandDefinition
{
    public function __construct(
        public int $lowerBound,
        public ?int $upperBound,
        public int $amountMinor,
        public bool $isUnbounded = false,
    ) {}

    public static function bounded(int $lowerBound, int $upperBound, int $amountMinor): self
    {
        return new self($lowerBound, $upperBound, $amountMinor);
    }

    public static function unbounded(int $lowerBound, int $amountMinor): self
    {
        return new self($lowerBound, null, $amountMinor, true);
    }

    public function contains(int $value): bool
    {
        if ($value < $this->lowerBound) {
            return false;
        }

        return $this->isUnbounded || $value < (int) $this->upperBound;
    }
}
