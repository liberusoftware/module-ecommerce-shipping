<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Liberu\Ecommerce\Shipping\Actions\QuoteShippingOptions;
use Liberu\Ecommerce\Shipping\Actions\SaveRate;
use Liberu\Ecommerce\Shipping\Actions\SaveRestriction;
use Liberu\Ecommerce\Shipping\Actions\SaveZone;
use Liberu\Ecommerce\Shipping\Contracts\FetchesCarrierRates;
use Liberu\Ecommerce\Shipping\Data\BandDefinition;
use Liberu\Ecommerce\Shipping\Data\Destination;
use Liberu\Ecommerce\Shipping\Data\Parcel;
use Liberu\Ecommerce\Shipping\Data\ParcelSet;
use Liberu\Ecommerce\Shipping\Data\RateDefinition;
use Liberu\Ecommerce\Shipping\Data\RestrictionDefinition;
use Liberu\Ecommerce\Shipping\Data\ShippingOptions;
use Liberu\Ecommerce\Shipping\Data\TerritoryDefinition;
use Liberu\Ecommerce\Shipping\Data\TransitEstimate;
use Liberu\Ecommerce\Shipping\Data\ZoneDefinition;
use Liberu\Ecommerce\Shipping\Enums\BandAxis;
use Liberu\Ecommerce\Shipping\Enums\RateType;
use Liberu\Ecommerce\Shipping\Enums\RestrictionType;
use Liberu\Ecommerce\Shipping\Enums\TransitBasis;
use Liberu\Ecommerce\Shipping\Models\Rate;
use Liberu\Ecommerce\Shipping\Models\Restriction;
use Liberu\Ecommerce\Shipping\Models\ServiceLevel;
use Liberu\Ecommerce\Shipping\Models\Zone;
use Liberu\Ecommerce\Shipping\Queries\FindZoneForDestination;
use Liberu\PackageTestbench\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    use RefreshDatabase;

    protected string $tenant = 'tenant-alpha';

    protected function zone(string $code, int $precedence = 0, ?array $territories = null, ?string $tenant = null, bool $isActive = true): Zone
    {
        return (new SaveZone())($tenant ?? $this->tenant, new ZoneDefinition(
            $code,
            ucfirst($code),
            $precedence,
            $territories ?? [new TerritoryDefinition('GB')],
            $isActive,
        ));
    }

    protected function serviceLevel(string $code = 'standard', string $name = 'Standard', ?string $tenant = null, bool $isActive = true): ServiceLevel
    {
        return ServiceLevel::query()->create([
            'tenant_id' => $tenant ?? $this->tenant,
            'code' => $code,
            'name' => $name,
            'is_active' => $isActive,
        ]);
    }

    protected function estimate(int $min = 2, int $max = 4, TransitBasis $basis = TransitBasis::BusinessDays): TransitEstimate
    {
        return new TransitEstimate($min, $max, $basis);
    }

    protected function flatRate(Zone $zone, ServiceLevel $serviceLevel, int $amountMinor, ?int $freeAbove = null, string $currency = 'GBP'): Rate
    {
        return (new SaveRate())($zone->tenant_id, new RateDefinition(
            zoneId: $zone->id,
            serviceLevelId: $serviceLevel->id,
            rateType: RateType::Flat,
            currency: $currency,
            estimate: $this->estimate(),
            amountMinor: $amountMinor,
            freeAboveSubtotalMinor: $freeAbove,
        ));
    }

    /** @param  list<BandDefinition>  $bands */
    protected function tableRate(Zone $zone, ServiceLevel $serviceLevel, BandAxis $axis, array $bands, string $currency = 'GBP'): Rate
    {
        return (new SaveRate())($zone->tenant_id, new RateDefinition(
            zoneId: $zone->id,
            serviceLevelId: $serviceLevel->id,
            rateType: RateType::Table,
            currency: $currency,
            estimate: $this->estimate(),
            bandAxis: $axis,
            bands: $bands,
        ));
    }

    protected function restriction(RestrictionType $type, ?int $threshold, string $reasonCode, string $reason, ?Zone $zone = null, ?ServiceLevel $serviceLevel = null): Restriction
    {
        return (new SaveRestriction())($this->tenant, new RestrictionDefinition(
            restrictionType: $type,
            reasonCode: $reasonCode,
            reason: $reason,
            zoneId: $zone?->id,
            serviceLevelId: $serviceLevel?->id,
            threshold: $threshold,
        ));
    }

    protected function parcels(int $grams = 1_000, ?int $lengthMm = null, ?int $widthMm = null, ?int $heightMm = null): ParcelSet
    {
        return new ParcelSet(new Parcel($grams, $lengthMm, $widthMm, $heightMm));
    }

    protected function quote(
        ?Destination $destination = null,
        ?ParcelSet $parcels = null,
        string $currency = 'GBP',
        ?int $subtotalMinor = null,
        ?int $itemCount = null,
        ?FetchesCarrierRates $carrier = null,
        int $ttlMinutes = 30,
    ): ShippingOptions {
        $action = new QuoteShippingOptions(new FindZoneForDestination(), $carrier, $ttlMinutes);

        return $action(
            $this->tenant,
            $destination ?? new Destination('GB', null, 'SW1A 1AA'),
            $parcels ?? $this->parcels(),
            $currency,
            $subtotalMinor,
            $itemCount,
        );
    }
}
