<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingDisabled;
use Liberu\Ecommerce\Shipping\Data\BandDefinition;
use Liberu\Ecommerce\Shipping\Data\Destination;
use Liberu\Ecommerce\Shipping\Data\Parcel;
use Liberu\Ecommerce\Shipping\Data\ParcelSet;
use Liberu\Ecommerce\Shipping\Data\TerritoryDefinition;
use Liberu\Ecommerce\Shipping\Enums\AppliedRule;
use Liberu\Ecommerce\Shipping\Enums\BandAxis;
use Liberu\Ecommerce\Shipping\Enums\PriceKind;
use Liberu\Ecommerce\Shipping\Enums\PriceStatus;
use Liberu\Ecommerce\Shipping\Enums\QuoteOutcome;
use Liberu\Ecommerce\Shipping\Enums\RestrictionType;
use Liberu\Ecommerce\Shipping\Events\ShippingOptionsQuoted;
use Liberu\Ecommerce\Shipping\Exceptions\InvalidMoney;
use Liberu\Ecommerce\Shipping\Exceptions\QuoteInputMissing;
use Liberu\Ecommerce\Shipping\Models\ShippingPrice;

it('prices a flat rate against the zone the destination falls in', function () {
    $this->flatRate($this->zone('uk', 0, [new TerritoryDefinition('GB')]), $this->serviceLevel(), 499);

    $options = $this->quote();

    expect($options->outcome)->toBe(QuoteOutcome::OptionsAvailable)
        ->and($options->zoneCode)->toBe('uk')
        ->and($options->available)->toHaveCount(1)
        ->and($options->available[0]->amount->minor)->toBe(499)
        ->and($options->available[0]->kind)->toBe(PriceKind::Derived)
        ->and($options->available[0]->appliedRule)->toBe(AppliedRule::Flat)
        ->and($options->carrierOutcome)->toBeInstanceOf(CarrierRatingDisabled::class);
});

it('records every option it offers, with the destination and the parcel it was priced against', function () {
    $this->flatRate($this->zone('uk'), $this->serviceLevel(), 499);

    $options = $this->quote(parcels: new ParcelSet(new Parcel(750, 300, 200, 100), new Parcel(250)));
    $price = ShippingPrice::query()->where('reference', $options->available[0]->reference)->firstOrFail();

    expect($price->status)->toBe(PriceStatus::Offered)
        ->and($price->kind)->toBe(PriceKind::Derived)
        ->and($price->destination_country)->toBe('GB')
        ->and($price->destination_postcode)->toBe('SW1A1AA')
        ->and($price->parcels)->toHaveCount(2)
        ->and($price->parcels[0]->weight_grams)->toBe(750)
        ->and($price->parcels[0]->length_mm)->toBe(300)
        ->and($price->expires_at->isFuture())->toBeTrue();
});

it('looks a table rate up on the band the weight falls in', function () {
    $this->tableRate($this->zone('uk'), $this->serviceLevel(), BandAxis::WeightGrams, [
        BandDefinition::bounded(0, 1_000, 399),
        BandDefinition::bounded(1_000, 5_000, 799),
        BandDefinition::unbounded(5_000, 1_500),
    ]);

    expect($this->quote(parcels: $this->parcels(999))->available[0]->amount->minor)->toBe(399)
        ->and($this->quote(parcels: $this->parcels(1_000))->available[0]->amount->minor)->toBe(799)
        ->and($this->quote(parcels: $this->parcels(40_000))->available[0]->amount->minor)->toBe(1_500);
});

it('records which band produced a table price', function () {
    $rate = $this->tableRate($this->zone('uk'), $this->serviceLevel(), BandAxis::WeightGrams, [
        BandDefinition::bounded(0, 1_000, 399),
        BandDefinition::unbounded(1_000, 999),
    ]);

    $options = $this->quote(parcels: $this->parcels(2_000));
    $price = ShippingPrice::query()->where('reference', $options->available[0]->reference)->firstOrFail();

    expect($price->applied_rule)->toBe(AppliedRule::TableBand)
        ->and($price->rate_id)->toBe($rate->id)
        ->and($price->rate_band_id)->toBe($rate->bands()->where('is_unbounded', true)->firstOrFail()->id);
});

it('bands a table rate on the order subtotal or the item count too', function () {
    $zone = $this->zone('uk');
    $this->tableRate($zone, $this->serviceLevel('subtotal-banded', 'Subtotal banded'), BandAxis::SubtotalMinor, [
        BandDefinition::bounded(0, 5_000, 599),
        BandDefinition::unbounded(5_000, 299),
    ]);
    $this->tableRate($zone, $this->serviceLevel('count-banded', 'Count banded'), BandAxis::ItemCount, [
        BandDefinition::bounded(0, 3, 100),
        BandDefinition::unbounded(3, 250),
    ]);

    $options = $this->quote(subtotalMinor: 6_000, itemCount: 4);
    $amounts = array_map(fn ($option): int => $option->amount->minor, $options->available);

    sort($amounts);

    expect($amounts)->toBe([250, 299]);
});

it('refuses to price a subtotal-banded rate when no subtotal was supplied', function () {
    $this->tableRate($this->zone('uk'), $this->serviceLevel(), BandAxis::SubtotalMinor, [BandDefinition::unbounded(0, 599)]);

    $this->quote();
})->throws(QuoteInputMissing::class, 'not zero');

it('refuses to price an item-count-banded rate when no count was supplied', function () {
    $this->tableRate($this->zone('uk'), $this->serviceLevel(), BandAxis::ItemCount, [BandDefinition::unbounded(0, 599)]);

    $this->quote();
})->throws(QuoteInputMissing::class, 'item count');

it('makes shipping free above a subtotal, as a rate rule and not as a discount', function () {
    $this->flatRate($this->zone('uk'), $this->serviceLevel(), 499, freeAbove: 5_000);

    $below = $this->quote(subtotalMinor: 4_999);
    $above = $this->quote(subtotalMinor: 5_000);

    expect($below->available[0]->amount->minor)->toBe(499)
        ->and($below->available[0]->appliedRule)->toBe(AppliedRule::Flat)
        ->and($above->available[0]->amount->minor)->toBe(0)
        ->and($above->available[0]->isFree())->toBeTrue()
        ->and($above->available[0]->appliedRule)->toBe(AppliedRule::FreeThreshold);
});

it('refuses a free-shipping threshold with no subtotal rather than treating it as zero', function () {
    $this->flatRate($this->zone('uk'), $this->serviceLevel(), 499, freeAbove: 5_000);

    $this->quote();
})->throws(QuoteInputMissing::class);

it('returns an excluded option carrying the restriction that excluded it', function () {
    $zone = $this->zone('uk');
    $this->flatRate($zone, $this->serviceLevel('express', 'Express'), 999);
    $this->restriction(RestrictionType::MaxWeightGrams, 2_000, 'too_heavy', 'Express is limited to 2kg per shipment.');

    $options = $this->quote(parcels: $this->parcels(2_500));

    expect($options->outcome)->toBe(QuoteOutcome::AllExcluded)
        ->and($options->available)->toBe([])
        ->and($options->excluded)->toHaveCount(1)
        ->and($options->excluded[0]->serviceLevelCode)->toBe('express')
        ->and($options->excluded[0]->reasonCode)->toBe('too_heavy')
        ->and($options->excluded[0]->reason)->toContain('2kg')
        ->and($options->excluded[0]->restrictionType)->toBe(RestrictionType::MaxWeightGrams);
});

it('excludes on a dimension, on a minimum subtotal, and on the destination itself', function (RestrictionType $type, ?int $threshold) {
    $zone = $this->zone('uk');
    $this->flatRate($zone, $this->serviceLevel(), 499);
    $this->restriction($type, $threshold, 'nope', 'Not available for this shipment.');

    $options = $this->quote(parcels: $this->parcels(500, 1_500, 100, 100), subtotalMinor: 100);

    expect($options->excluded)->toHaveCount(1)
        ->and($options->outcome)->toBe(QuoteOutcome::AllExcluded);
})->with([
    [RestrictionType::MaxDimensionMm, 1_000],
    [RestrictionType::MinSubtotalMinor, 5_000],
    [RestrictionType::DestinationExcluded, null],
]);

it('applies a restriction scoped to one service level to that service level only', function () {
    $zone = $this->zone('uk');
    $express = $this->serviceLevel('express', 'Express');
    $this->flatRate($zone, $this->serviceLevel(), 499);
    $this->flatRate($zone, $express, 999);
    $this->restriction(RestrictionType::MaxWeightGrams, 1_000, 'too_heavy', 'Too heavy for Express.', serviceLevel: $express);

    $options = $this->quote(parcels: $this->parcels(5_000));

    expect($options->available)->toHaveCount(1)
        ->and($options->available[0]->serviceLevelCode)->toBe('standard')
        ->and($options->excluded)->toHaveCount(1)
        ->and($options->excluded[0]->serviceLevelCode)->toBe('express')
        ->and($options->outcome)->toBe(QuoteOutcome::OptionsAvailable);
});

it('reports a destination no zone covers as its own outcome, not as an empty list', function () {
    $this->flatRate($this->zone('uk', 0, [new TerritoryDefinition('GB')]), $this->serviceLevel(), 499);

    $options = $this->quote(destination: new Destination('JP'));

    expect($options->outcome)->toBe(QuoteOutcome::NoZoneMatched)
        ->and($options->available)->toBe([])
        ->and($options->excluded)->toBe([])
        ->and($options->zoneCode)->toBeNull();
});

it('reports a zone with no rates as its own outcome', function () {
    $this->zone('uk');

    expect($this->quote()->outcome)->toBe(QuoteOutcome::NoRatesConfigured);
});

it('ignores an inactive rate and an inactive service level', function () {
    $zone = $this->zone('uk');
    $rate = $this->flatRate($zone, $this->serviceLevel(), 499);
    $rate->update(['is_active' => false]);
    $this->flatRate($zone, $this->serviceLevel('retired', 'Retired', isActive: false), 999);

    expect($this->quote()->available)->toBe([]);
});

it('refuses to quote a rate in another currency', function () {
    $this->flatRate($this->zone('uk'), $this->serviceLevel(), 499, currency: 'USD');

    $this->quote(currency: 'GBP');
})->throws(InvalidMoney::class);

it('offers the cheapest option and finds an option by reference', function () {
    $zone = $this->zone('uk');
    $this->flatRate($zone, $this->serviceLevel(), 499);
    $this->flatRate($zone, $this->serviceLevel('express', 'Express'), 999);

    $options = $this->quote();

    expect($options->cheapest()?->amount->minor)->toBe(499)
        ->and($options->reference($options->available[1]->reference)?->serviceLevelCode)->toBe('express')
        ->and($options->reference('shp_nothing'))->toBeNull();
});

it('publishes what it quoted', function () {
    Event::fake();
    $this->flatRate($this->zone('uk'), $this->serviceLevel(), 499);

    $this->quote();

    Event::assertDispatched(ShippingOptionsQuoted::class, fn (ShippingOptionsQuoted $event): bool => $event->tenantId === $this->tenant
        && $event->options->outcome === QuoteOutcome::OptionsAvailable);
});

it('never offers another tenant rate', function () {
    $this->flatRate($this->zone('uk', tenant: 'tenant-beta'), $this->serviceLevel(tenant: 'tenant-beta'), 499);

    expect($this->quote()->outcome)->toBe(QuoteOutcome::NoZoneMatched);
});
