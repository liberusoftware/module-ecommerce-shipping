<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Data;

use Liberu\Ecommerce\Shipping\Exceptions\InvalidDestination;

/**
 * Where a shipment is going, as the predicates a zone can match on.
 *
 * There is no coordinate pair and no radius here: a zone is a set of
 * destination predicates, and this module computes no travel distance at all.
 */
final readonly class Destination
{
    public string $countryCode;

    public ?string $subdivisionCode;

    public ?string $postcode;

    public function __construct(string $countryCode, ?string $subdivisionCode = null, ?string $postcode = null)
    {
        $country = strtoupper(trim($countryCode));

        if (preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            throw InvalidDestination::country($countryCode);
        }

        $this->countryCode = $country;
        $this->subdivisionCode = self::normalise($subdivisionCode);
        $this->postcode = self::normalise($postcode);
    }

    public function describe(): string
    {
        return implode(' ', array_filter([$this->countryCode, $this->subdivisionCode, $this->postcode]));
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
