<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Exceptions;

final class InvalidParcel extends ShippingException
{
    public static function nonPositiveWeight(int $grams): self
    {
        return new self("A parcel weighing {$grams}g is not a parcel. Weight is integer grams and must be positive.");
    }

    public static function partialDimensions(): self
    {
        return new self('A parcel declares some dimensions but not others. Give length, width and height in millimetres, or none of them.');
    }

    public static function nonPositiveDimension(int $mm): self
    {
        return new self("A parcel dimension of {$mm}mm is not a dimension. Dimensions are integer millimetres and must be positive.");
    }

    public static function emptySet(): self
    {
        return new self('A parcel set with no parcels cannot be priced.');
    }
}
