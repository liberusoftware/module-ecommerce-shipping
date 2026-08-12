<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Data;

use Liberu\Ecommerce\Shipping\Exceptions\InvalidParcel;
use Liberu\Ecommerce\Shipping\Exceptions\ParcelWeightMissing;

/**
 * A box to be priced: integer grams, integer millimetres.
 *
 * One unit, at the boundary, always. An adapter that must speak ounces converts
 * inside itself and tests the conversion; nothing here carries a unit column.
 */
final readonly class Parcel
{
    public function __construct(
        public int $weightGrams,
        public ?int $lengthMm = null,
        public ?int $widthMm = null,
        public ?int $heightMm = null,
    ) {
        if ($weightGrams <= 0) {
            throw InvalidParcel::nonPositiveWeight($weightGrams);
        }

        $dimensions = [$lengthMm, $widthMm, $heightMm];
        $given = array_filter($dimensions, static fn (?int $mm): bool => $mm !== null);

        if ($given !== [] && count($given) !== 3) {
            throw InvalidParcel::partialDimensions();
        }

        foreach ($given as $mm) {
            if ($mm <= 0) {
                throw InvalidParcel::nonPositiveDimension($mm);
            }
        }
    }

    /**
     * The only way a null weight may reach this module: refused, with its own
     * exception, rather than defaulted to zero.
     */
    public static function fromNullableWeight(?int $weightGrams, ?int $lengthMm = null, ?int $widthMm = null, ?int $heightMm = null, ?string $reference = null): self
    {
        if ($weightGrams === null) {
            throw ParcelWeightMissing::forParcel($reference);
        }

        return new self($weightGrams, $lengthMm, $widthMm, $heightMm);
    }

    public function hasDimensions(): bool
    {
        return $this->lengthMm !== null;
    }

    public function longestSideMm(): ?int
    {
        return $this->hasDimensions() ? max((int) $this->lengthMm, (int) $this->widthMm, (int) $this->heightMm) : null;
    }
}
