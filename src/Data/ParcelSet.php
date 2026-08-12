<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Data;

use Countable;
use Liberu\Ecommerce\Shipping\Exceptions\InvalidParcel;

/** The parcels a single shipment is priced against. */
final readonly class ParcelSet implements Countable
{
    /** @var list<Parcel> */
    public array $parcels;

    public function __construct(Parcel ...$parcels)
    {
        if ($parcels === []) {
            throw InvalidParcel::emptySet();
        }

        $this->parcels = array_values($parcels);
    }

    public function totalWeightGrams(): int
    {
        return array_sum(array_map(static fn (Parcel $parcel): int => $parcel->weightGrams, $this->parcels));
    }

    public function longestSideMm(): ?int
    {
        $sides = array_filter(array_map(static fn (Parcel $parcel): ?int => $parcel->longestSideMm(), $this->parcels));

        return $sides === [] ? null : max($sides);
    }

    public function count(): int
    {
        return count($this->parcels);
    }
}
