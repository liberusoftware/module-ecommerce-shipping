<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Exceptions;

/** Refused at write time, naming both zones, because read-time ordering is unauditable. */
final class ZoneOverlapsExistingZone extends ShippingException
{
    private function __construct(
        string $message,
        public readonly string $zoneCode,
        public readonly string $conflictingZoneCode,
        public readonly int $precedence,
    ) {
        parent::__construct($message);
    }

    public static function at(string $zoneCode, string $conflictingZoneCode, int $precedence, string $territory): self
    {
        return new self(
            "Zone [{$zoneCode}] would match [{$territory}] at precedence {$precedence}, which zone [{$conflictingZoneCode}] already matches. Give one of them a different precedence, or narrow its territories.",
            $zoneCode,
            $conflictingZoneCode,
            $precedence,
        );
    }
}
