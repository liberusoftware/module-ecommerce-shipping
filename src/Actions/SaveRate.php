<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Actions;

use Illuminate\Support\Facades\DB;
use Liberu\Ecommerce\Shipping\Data\BandDefinition;
use Liberu\Ecommerce\Shipping\Data\RateDefinition;
use Liberu\Ecommerce\Shipping\Enums\RateType;
use Liberu\Ecommerce\Shipping\Exceptions\InvalidRateDefinition;
use Liberu\Ecommerce\Shipping\Exceptions\TenantMismatch;
use Liberu\Ecommerce\Shipping\Models\Rate;
use Liberu\Ecommerce\Shipping\Models\ServiceLevel;
use Liberu\Ecommerce\Shipping\Models\Zone;
use Liberu\Ecommerce\Shipping\Support\BandTiling;

/**
 * Prices a service level in a zone.
 *
 * Everything that can be wrong with a rate is refused here rather than at quote
 * time: a table whose bands do not tile its axis is a price nobody can produce,
 * and discovering that at checkout tells the one person who cannot fix it.
 */
final class SaveRate
{
    public function __invoke(string $tenantId, RateDefinition $definition, ?int $rateId = null): Rate
    {
        $this->assertOwned($tenantId, $definition);
        $bands = $this->assertUsable($definition);

        return DB::transaction(function () use ($tenantId, $definition, $bands, $rateId): Rate {
            $rate = ($rateId !== null
                ? Rate::query()->where('tenant_id', $tenantId)->find($rateId)
                : Rate::query()
                    ->where('tenant_id', $tenantId)
                    ->where('zone_id', $definition->zoneId)
                    ->where('service_level_id', $definition->serviceLevelId)
                    ->first()) ?? new Rate();

            $rate->fill([
                'tenant_id' => $tenantId,
                'zone_id' => $definition->zoneId,
                'service_level_id' => $definition->serviceLevelId,
                'rate_type' => $definition->rateType,
                'amount_minor' => $definition->amountMinor,
                'currency' => strtoupper($definition->currency),
                'band_axis' => $definition->bandAxis,
                'free_above_subtotal_minor' => $definition->freeAboveSubtotalMinor,
                'transit_min_days' => $definition->estimate->minDays,
                'transit_max_days' => $definition->estimate->maxDays,
                'transit_basis' => $definition->estimate->basis,
                'is_active' => $definition->isActive,
            ])->save();

            $rate->bands()->delete();

            foreach ($bands as $band) {
                $rate->bands()->create([
                    'tenant_id' => $tenantId,
                    'lower_bound' => $band->lowerBound,
                    'upper_bound' => $band->upperBound,
                    'is_unbounded' => $band->isUnbounded,
                    'amount_minor' => $band->amountMinor,
                ]);
            }

            return $rate->refresh();
        });
    }

    /** @return list<BandDefinition> */
    private function assertUsable(RateDefinition $definition): array
    {
        if ($definition->freeAboveSubtotalMinor !== null && $definition->freeAboveSubtotalMinor < 0) {
            throw InvalidRateDefinition::negativeThreshold('free_above_subtotal_minor');
        }

        if ($definition->rateType === RateType::Flat) {
            if ($definition->amountMinor === null) {
                throw InvalidRateDefinition::flatRateNeedsAmount();
            }

            if ($definition->bands !== []) {
                throw InvalidRateDefinition::flatRateTakesNoBands();
            }

            return [];
        }

        if ($definition->bandAxis === null) {
            throw InvalidRateDefinition::tableRateNeedsAxis();
        }

        return BandTiling::assertTiles($definition->bandAxis, $definition->bands);
    }

    private function assertOwned(string $tenantId, RateDefinition $definition): void
    {
        $zone = Zone::query()->findOrFail($definition->zoneId);

        if ($zone->tenant_id !== $tenantId) {
            throw TenantMismatch::make($tenantId, $zone->tenant_id);
        }

        $serviceLevel = ServiceLevel::query()->findOrFail($definition->serviceLevelId);

        if ($serviceLevel->tenant_id !== $tenantId) {
            throw TenantMismatch::make($tenantId, $serviceLevel->tenant_id);
        }
    }
}
