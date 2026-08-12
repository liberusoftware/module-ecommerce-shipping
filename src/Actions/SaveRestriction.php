<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Actions;

use Liberu\Ecommerce\Shipping\Data\RestrictionDefinition;
use Liberu\Ecommerce\Shipping\Enums\RestrictionType;
use Liberu\Ecommerce\Shipping\Exceptions\InvalidRateDefinition;
use Liberu\Ecommerce\Shipping\Models\Restriction;

/**
 * Records a rule that excludes a service level, together with the reason a
 * buyer will be shown. A restriction with no reason is a silent filter, so the
 * reason is not optional.
 */
final class SaveRestriction
{
    public function __invoke(string $tenantId, RestrictionDefinition $definition, ?int $restrictionId = null): Restriction
    {
        if ($definition->restrictionType !== RestrictionType::DestinationExcluded) {
            if ($definition->threshold === null || $definition->threshold < 0) {
                throw InvalidRateDefinition::negativeThreshold($definition->restrictionType->value);
            }
        }

        $restriction = ($restrictionId !== null
            ? Restriction::query()->where('tenant_id', $tenantId)->find($restrictionId)
            : null) ?? new Restriction();

        $restriction->fill([
            'tenant_id' => $tenantId,
            'zone_id' => $definition->zoneId,
            'service_level_id' => $definition->serviceLevelId,
            'restriction_type' => $definition->restrictionType,
            'threshold' => $definition->threshold,
            'reason_code' => $definition->reasonCode,
            'reason' => $definition->reason,
            'is_active' => $definition->isActive,
        ])->save();

        return $restriction->refresh();
    }
}
