<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Data;

use Liberu\Ecommerce\Shipping\Exceptions\InvalidDestination;

/** One destination predicate belonging to a zone. */
final readonly class TerritoryDefinition
{
    public string $countryCode;

    public ?string $subdivisionCode;

    public ?string $postcodePrefix;

    public function __construct(string $countryCode, ?string $subdivisionCode = null, ?string $postcodePrefix = null)
    {
        $country = strtoupper(trim($countryCode));

        if (preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            throw InvalidDestination::country($countryCode);
        }

        $this->countryCode = $country;
        $this->subdivisionCode = self::normalise($subdivisionCode);
        $this->postcodePrefix = self::normalise($postcodePrefix);
    }

    public function matches(Destination $destination): bool
    {
        if ($destination->countryCode !== $this->countryCode) {
            return false;
        }

        if ($this->subdivisionCode !== null && $destination->subdivisionCode !== $this->subdivisionCode) {
            return false;
        }

        if ($this->postcodePrefix !== null) {
            return $destination->postcode !== null && str_starts_with($destination->postcode, $this->postcodePrefix);
        }

        return true;
    }

    /**
     * Whether some destination exists that both territories would match.
     *
     * Decided here rather than by sampling addresses: a null predicate is a
     * wildcard, and two postcode prefixes intersect exactly when one is a prefix
     * of the other.
     */
    public function overlaps(self $other): bool
    {
        if ($this->countryCode !== $other->countryCode) {
            return false;
        }

        if ($this->subdivisionCode !== null && $other->subdivisionCode !== null && $this->subdivisionCode !== $other->subdivisionCode) {
            return false;
        }

        if ($this->postcodePrefix === null || $other->postcodePrefix === null) {
            return true;
        }

        return str_starts_with($this->postcodePrefix, $other->postcodePrefix)
            || str_starts_with($other->postcodePrefix, $this->postcodePrefix);
    }

    public function describe(): string
    {
        return implode('/', array_filter([$this->countryCode, $this->subdivisionCode, $this->postcodePrefix]));
    }

    private static function normalise(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalised = strtoupper((string) preg_replace('/\s+/', '', $value));

        return $normalised === '' ? null : $normalised;
    }
}
