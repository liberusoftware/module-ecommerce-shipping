<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Queries;

use Liberu\Ecommerce\Shipping\Data\DerivedProvenance;
use Liberu\Ecommerce\Shipping\Data\Parcel;
use Liberu\Ecommerce\Shipping\Data\PriceProvenance;
use Liberu\Ecommerce\Shipping\Data\QuotedProvenance;
use Liberu\Ecommerce\Shipping\Data\RecordedAdjustment;
use Liberu\Ecommerce\Shipping\Data\RecordedPrice;
use Liberu\Ecommerce\Shipping\Enums\AppliedRule;
use Liberu\Ecommerce\Shipping\Enums\PriceKind;
use Liberu\Ecommerce\Shipping\Exceptions\UnknownShippingPrice;
use Liberu\Ecommerce\Shipping\Models\PriceAdjustment;
use Liberu\Ecommerce\Shipping\Models\PriceParcel;
use Liberu\Ecommerce\Shipping\Models\ShippingPrice;

/** A recorded price read back whole, with its provenance, parcels and adjustment lines. */
final class GetShippingPrice
{
    public function __invoke(string $tenantId, string $reference): RecordedPrice
    {
        $price = ShippingPrice::query()
            ->with(['adjustments', 'parcels'])
            ->where('tenant_id', $tenantId)
            ->where('reference', $reference)
            ->first();

        if (! $price instanceof ShippingPrice) {
            throw UnknownShippingPrice::reference($reference);
        }

        return new RecordedPrice(
            reference: $price->reference,
            kind: $price->kind,
            status: $price->status,
            amount: $price->money(),
            estimate: $price->estimate(),
            serviceLevelCode: $price->service_level_code,
            serviceLevelName: $price->service_level_name,
            destination: $price->destination(),
            provenance: self::provenance($price),
            adjustments: $price->adjustments
                ->map(static fn (PriceAdjustment $adjustment): RecordedAdjustment => new RecordedAdjustment(
                    $adjustment->id,
                    $adjustment->money(),
                    $adjustment->reason_code,
                    $adjustment->reason,
                    $adjustment->basis_points,
                ))
                ->values()
                ->all(),
            parcels: $price->parcels->map(static fn (PriceParcel $parcel): Parcel => $parcel->toParcel())->values()->all(),
            selectedAt: $price->selected_at?->toIso8601String(),
            expiresAt: $price->expires_at->toIso8601String(),
        );
    }

    /**
     * The discriminator decides which shape is read, so a row whose provenance
     * columns disagree with its kind cannot be read as the other one.
     */
    private static function provenance(ShippingPrice $price): PriceProvenance
    {
        if ($price->kind === PriceKind::Quoted) {
            return new QuotedProvenance(
                (string) $price->carrier_code,
                (string) $price->carrier_service_code,
                $price->carrier_rate_reference,
                (string) $price->quoted_at?->toIso8601String(),
            );
        }

        return new DerivedProvenance(
            (int) $price->zone_id,
            (string) $price->zone_code,
            (int) $price->rate_id,
            $price->applied_rule ?? AppliedRule::Flat,
            $price->rate_band_id,
        );
    }
}
