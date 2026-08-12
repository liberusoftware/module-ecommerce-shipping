<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Queries;

use Liberu\Ecommerce\Shipping\Data\Destination;
use Liberu\Ecommerce\Shipping\Models\Zone;

/**
 * The zone that owns a destination, by explicit precedence.
 *
 * Higher precedence wins. The result is deterministic because ambiguity was
 * refused when the second zone was saved, not sorted out here — ordering
 * resolved at read time is ordering nobody can audit.
 */
final class FindZoneForDestination
{
    public function __invoke(string $tenantId, Destination $destination): ?Zone
    {
        $candidates = Zone::query()
            ->with('territories')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByDesc('precedence')
            ->orderBy('id')
            ->get();

        foreach ($candidates as $zone) {
            foreach ($zone->territories as $territory) {
                if ($territory->toDefinition()->matches($destination)) {
                    return $zone;
                }
            }
        }

        return null;
    }
}
