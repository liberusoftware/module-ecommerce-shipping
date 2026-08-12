<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Data;

use Liberu\Ecommerce\Shipping\Enums\RestrictionType;

/**
 * A rule that excludes a service level, carrying the reason it will be shown
 * with. A restriction never silently filters.
 */
final readonly class RestrictionDefinition
{
    public function __construct(
        public RestrictionType $restrictionType,
        public string $reasonCode,
        public string $reason,
        public ?int $zoneId = null,
        public ?int $serviceLevelId = null,
        public ?int $threshold = null,
        public bool $isActive = true,
    ) {}
}
