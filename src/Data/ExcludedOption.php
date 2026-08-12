<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Data;

use Liberu\Ecommerce\Shipping\Enums\RestrictionType;

/**
 * A service level that was excluded, and why.
 *
 * Exclusions come back with the quote rather than being filtered out of it: a
 * buyer who cannot have next-day delivery is told the reason, not shown a
 * shorter list they cannot account for.
 */
final readonly class ExcludedOption
{
    public function __construct(
        public string $serviceLevelCode,
        public string $serviceLevelName,
        public RestrictionType $restrictionType,
        public string $reasonCode,
        public string $reason,
    ) {}
}
