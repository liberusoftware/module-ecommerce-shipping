<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Exceptions;

/**
 * A parcel with no weight is refused, never defaulted to zero.
 *
 * Defaulting is how a store that has not weighed its products gets quoted for a
 * lighter box than exists and eats the difference, with no warning at any layer.
 */
final class ParcelWeightMissing extends ShippingException
{
    public static function forParcel(?string $reference = null): self
    {
        return new self($reference === null
            ? 'A parcel was presented with no weight. Weigh it: a missing weight is never zero grams.'
            : "Parcel [{$reference}] was presented with no weight. Weigh it: a missing weight is never zero grams.");
    }
}
