<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Actions;

use Illuminate\Support\Facades\DB;
use Liberu\Ecommerce\Shipping\Data\ZoneDefinition;
use Liberu\Ecommerce\Shipping\Exceptions\ZoneOverlapsExistingZone;
use Liberu\Ecommerce\Shipping\Models\Zone;

/**
 * Writes a zone and its territories, refusing an ambiguous overlap.
 *
 * Two active zones that could match the same destination at the same precedence
 * are rejected here, when the second one is saved, and the refusal names both.
 * Overlap at *different* precedences is legitimate and is how a specific zone
 * beats a general one.
 */
final class SaveZone
{
    public function __invoke(string $tenantId, ZoneDefinition $definition, ?int $zoneId = null): Zone
    {
        $existing = $zoneId !== null
            ? Zone::query()->where('tenant_id', $tenantId)->find($zoneId)
            : Zone::query()->where('tenant_id', $tenantId)->where('code', $definition->code)->first();

        if ($definition->isActive) {
            $this->assertNoOverlap($tenantId, $definition, $existing?->id);
        }

        return DB::transaction(function () use ($tenantId, $definition, $existing): Zone {
            $zone = $existing ?? new Zone();

            $zone->fill([
                'tenant_id' => $tenantId,
                'code' => $definition->code,
                'name' => $definition->name,
                'precedence' => $definition->precedence,
                'is_active' => $definition->isActive,
            ])->save();

            $zone->territories()->delete();

            foreach ($definition->territories as $territory) {
                $zone->territories()->create([
                    'tenant_id' => $tenantId,
                    'country_code' => $territory->countryCode,
                    'subdivision_code' => $territory->subdivisionCode,
                    'postcode_prefix' => $territory->postcodePrefix,
                ]);
            }

            return $zone->refresh();
        });
    }

    private function assertNoOverlap(string $tenantId, ZoneDefinition $definition, ?int $ignoreZoneId): void
    {
        $rivals = Zone::query()
            ->with('territories')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('precedence', $definition->precedence)
            ->when($ignoreZoneId !== null, fn ($query) => $query->whereKeyNot($ignoreZoneId))
            ->get();

        foreach ($rivals as $rival) {
            foreach ($rival->territories as $territory) {
                $rivalTerritory = $territory->toDefinition();

                foreach ($definition->territories as $candidate) {
                    if ($candidate->overlaps($rivalTerritory)) {
                        throw ZoneOverlapsExistingZone::at(
                            $definition->code,
                            $rival->code,
                            $definition->precedence,
                            $candidate->describe(),
                        );
                    }
                }
            }
        }
    }
}
