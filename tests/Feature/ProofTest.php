<?php

declare(strict_types=1);

use Liberu\Ecommerce\Shipping\Actions\QuoteShippingOptions;
use Liberu\Ecommerce\Shipping\Actions\RecordPriceAdjustment;
use Liberu\Ecommerce\Shipping\Actions\SelectShippingPrice;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatesReturned;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingOutcome;
use Liberu\Ecommerce\Shipping\Contracts\FetchesCarrierRates;
use Liberu\Ecommerce\Shipping\Data\BandDefinition;
use Liberu\Ecommerce\Shipping\Data\CarrierRate;
use Liberu\Ecommerce\Shipping\Data\Destination;
use Liberu\Ecommerce\Shipping\Data\Money;
use Liberu\Ecommerce\Shipping\Data\Parcel;
use Liberu\Ecommerce\Shipping\Data\ParcelSet;
use Liberu\Ecommerce\Shipping\Data\QuotedProvenance;
use Liberu\Ecommerce\Shipping\Data\TerritoryDefinition;
use Liberu\Ecommerce\Shipping\Data\TransitEstimate;
use Liberu\Ecommerce\Shipping\Enums\BandAxis;
use Liberu\Ecommerce\Shipping\Enums\PriceKind;
use Liberu\Ecommerce\Shipping\Enums\RestrictionType;
use Liberu\Ecommerce\Shipping\Enums\TransitBasis;
use Liberu\Ecommerce\Shipping\Models\Rate;
use Liberu\Ecommerce\Shipping\Models\RateBand;
use Liberu\Ecommerce\Shipping\Models\Restriction;
use Liberu\Ecommerce\Shipping\Models\ShippingPrice;
use Liberu\Ecommerce\Shipping\Models\Zone;
use Liberu\Ecommerce\Shipping\Models\ZoneTerritory;
use Liberu\Ecommerce\Shipping\Queries\FindZoneForDestination;
use Liberu\Ecommerce\Shipping\Queries\GetShippingPrice;

/*
 * One fixture, not a hand-picked pair: mixed derived and quoted prices, a
 * surcharge, a restriction-excluded service level, and a zone overlap resolved
 * by precedence.
 */
beforeEach(function () {
    $uk = $this->zone('uk', 0, [new TerritoryDefinition('GB')]);
    $london = $this->zone('london', 10, [new TerritoryDefinition('GB', null, 'SW1')]);

    $standard = $this->serviceLevel('standard', 'Standard');
    $express = $this->serviceLevel('express', 'Express');
    $economy = $this->serviceLevel('economy', 'Economy');

    $this->flatRate($uk, $standard, 499, freeAbove: 5_000);
    $this->flatRate($london, $standard, 299);
    $this->tableRate($uk, $express, BandAxis::WeightGrams, [
        BandDefinition::bounded(0, 1_000, 899),
        BandDefinition::bounded(1_000, 5_000, 1_299),
        BandDefinition::unbounded(5_000, 2_499),
    ]);
    $this->tableRate($london, $economy, BandAxis::SubtotalMinor, [
        BandDefinition::bounded(0, 2_500, 199),
        BandDefinition::unbounded(2_500, 99),
    ]);
    $this->restriction(RestrictionType::MaxWeightGrams, 4_000, 'too_heavy', 'Express is limited to 4kg.', serviceLevel: $express);

    $carrier = new class() implements FetchesCarrierRates
    {
        public function fetch(string $tenantId, Destination $destination, ParcelSet $parcels): CarrierRatingOutcome
        {
            return new CarrierRatesReturned([
                new CarrierRate('acme', 'acme_next_day', 'Acme Next Day', new Money(1_234, 'GBP'), new TransitEstimate(1, 1, TransitBasis::BusinessDays), 'rate_9f2c'),
                new CarrierRate('acme', 'acme_two_day', 'Acme Two Day', new Money(777, 'GBP'), new TransitEstimate(2, 2, TransitBasis::BusinessDays), 'rate_1a3d'),
            ]);
        }
    };

    // Derived only.
    $this->quote(destination: new Destination('GB', null, 'M1 1AA'), parcels: $this->parcels(900), subtotalMinor: 1_000);
    $this->quote(destination: new Destination('GB', null, 'M1 1AA'), parcels: $this->parcels(6_000), subtotalMinor: 9_000);
    // Derived plus quoted, in the higher-precedence zone.
    $london = $this->quote(destination: new Destination('GB', null, 'SW1A 1AA'), parcels: new ParcelSet(new Parcel(1_500, 400, 300, 200)), subtotalMinor: 3_000, carrier: $carrier);
    // Quoted where no zone matches at all.
    $this->quote(destination: new Destination('JP'), parcels: $this->parcels(2_000), subtotalMinor: 8_000, carrier: $carrier);

    $selected = collect($london->available)->firstWhere(fn ($option): bool => $option->kind === PriceKind::Quoted);
    app(SelectShippingPrice::class)($this->tenant, $selected->reference);
    app(RecordPriceAdjustment::class)($this->tenant, $selected->reference, 'fuel', 'Fuel surcharge', basisPoints: 350);

    $this->selectedReference = $selected->reference;
});

it('builds a fixture worth proving anything about', function () {
    expect(ShippingPrice::query()->where('kind', PriceKind::Derived->value)->count())->toBeGreaterThanOrEqual(4)
        ->and(ShippingPrice::query()->where('kind', PriceKind::Quoted->value)->count())->toBe(4)
        ->and(Restriction::query()->count())->toBe(1);
});

it('proves one: every recorded price is exactly one of derived or quoted', function () {
    $prices = ShippingPrice::query()->get();

    expect($prices)->not->toBeEmpty();

    foreach ($prices as $price) {
        expect($price->kind)->toBeIn([PriceKind::Derived, PriceKind::Quoted]);

        if ($price->kind === PriceKind::Derived) {
            // The discriminator and the provenance columns agree, and neither is
            // inferred from the other being null.
            expect($price->zone_id)->not->toBeNull()
                ->and($price->rate_id)->not->toBeNull()
                ->and($price->zone_code)->not->toBeNull()
                ->and($price->applied_rule)->not->toBeNull()
                ->and($price->carrier_code)->toBeNull()
                ->and($price->carrier_service_code)->toBeNull()
                ->and($price->carrier_rate_reference)->toBeNull()
                ->and($price->quoted_at)->toBeNull();

            continue;
        }

        expect($price->carrier_code)->not->toBeNull()
            ->and($price->carrier_service_code)->not->toBeNull()
            ->and($price->quoted_at)->not->toBeNull()
            ->and($price->zone_id)->toBeNull()
            ->and($price->rate_id)->toBeNull()
            ->and($price->rate_band_id)->toBeNull()
            ->and($price->zone_code)->toBeNull();
    }
});

it('proves two: every derived price recomputes from the recorded rules with the carrier seam ripped out', function () {
    $derived = ShippingPrice::query()->with('parcels')->where('kind', PriceKind::Derived->value)->get()->all();

    expect($derived)->not->toBeEmpty();

    // No carrier at all: a derived price must not depend on one having answered.
    $quote = new QuoteShippingOptions(new FindZoneForDestination(), null, 30);

    foreach ($derived as $price) {
        $parcels = new ParcelSet(...array_map(
            static fn ($parcel): Parcel => $parcel->toParcel(),
            $price->parcels->all(),
        ));

        $options = $quote(
            $this->tenant,
            $price->destination(),
            $parcels,
            $price->currency,
            $price->subtotal_minor,
            $price->item_count,
        );

        $recomputed = collect($options->available)->firstWhere(fn ($option): bool => $option->serviceLevelCode === $price->service_level_code);

        expect($recomputed)->not->toBeNull("No option reproduced [{$price->service_level_code}] for [{$price->reference}].")
            ->and($recomputed->amount->minor)->toBe($price->amount_minor)
            ->and($recomputed->appliedRule)->toBe($price->applied_rule);
    }
});

it('proves three: every quoted price survives the rate tables being emptied', function () {
    $before = ShippingPrice::query()
        ->where('kind', PriceKind::Quoted->value)
        ->get()
        ->map(fn (ShippingPrice $price): array => [
            'reference' => $price->reference,
            'amount_minor' => $price->amount_minor,
            'carrier_code' => $price->carrier_code,
            'carrier_service_code' => $price->carrier_service_code,
            'carrier_rate_reference' => $price->carrier_rate_reference,
            'quoted_at' => $price->quoted_at?->toIso8601String(),
        ])
        ->all();

    expect($before)->toHaveCount(4);

    Restriction::query()->delete();
    RateBand::query()->delete();
    Rate::query()->delete();
    ZoneTerritory::query()->delete();
    Zone::query()->delete();

    expect(Zone::query()->count())->toBe(0)
        ->and(Rate::query()->count())->toBe(0);

    foreach ($before as $snapshot) {
        $price = app(GetShippingPrice::class)($this->tenant, $snapshot['reference']);

        expect($price->kind)->toBe(PriceKind::Quoted)
            ->and($price->amount->minor)->toBe($snapshot['amount_minor'])
            ->and($price->provenance)->toBeInstanceOf(QuotedProvenance::class)
            ->and($price->provenance->carrierCode)->toBe($snapshot['carrier_code'])
            ->and($price->provenance->carrierServiceCode)->toBe($snapshot['carrier_service_code'])
            ->and($price->provenance->carrierRateReference)->toBe($snapshot['carrier_rate_reference'])
            ->and($price->provenance->quotedAt)->toBe($snapshot['quoted_at']);
    }
});

it('folds a shipment charge from its price line and its adjustment lines', function () {
    $price = app(GetShippingPrice::class)($this->tenant, $this->selectedReference);

    expect($price->amount->minor)->toBe(1_234)
        ->and($price->adjustments)->toHaveCount(1)
        ->and($price->adjustments[0]->amount->minor)->toBe(43)
        ->and($price->total()->minor)->toBe(1_277);
});

it('keeps a selected quoted price readable after the rules are gone, adjustments and all', function () {
    Zone::query()->delete();

    $price = app(GetShippingPrice::class)($this->tenant, $this->selectedReference);

    expect($price->total()->minor)->toBe(1_277)
        ->and($price->parcels)->toHaveCount(1)
        ->and($price->parcels[0]->weightGrams)->toBe(1_500)
        ->and($price->destination->postcode)->toBe('SW1A1AA');
});
