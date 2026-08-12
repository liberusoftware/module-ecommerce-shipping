<?php

declare(strict_types=1);

use Liberu\Ecommerce\Shipping\Actions\SaveRate;
use Liberu\Ecommerce\Shipping\Data\BandDefinition;
use Liberu\Ecommerce\Shipping\Data\RateDefinition;
use Liberu\Ecommerce\Shipping\Enums\BandAxis;
use Liberu\Ecommerce\Shipping\Enums\RateType;
use Liberu\Ecommerce\Shipping\Exceptions\InvalidRateDefinition;
use Liberu\Ecommerce\Shipping\Exceptions\RateBandsDoNotTileAxis;
use Liberu\Ecommerce\Shipping\Exceptions\TenantMismatch;

it('records a flat rate with its estimate', function () {
    $rate = $this->flatRate($this->zone('uk'), $this->serviceLevel(), 499);

    expect($rate->amount_minor)->toBe(499)
        ->and($rate->currency)->toBe('GBP')
        ->and($rate->estimate()->describe())->toBe('2-4 business days');
});

it('refuses a flat rate with no amount', function () {
    (new SaveRate())($this->tenant, new RateDefinition(
        zoneId: $this->zone('uk')->id,
        serviceLevelId: $this->serviceLevel()->id,
        rateType: RateType::Flat,
        currency: 'GBP',
        estimate: $this->estimate(),
    ));
})->throws(InvalidRateDefinition::class, 'must declare an amount');

it('refuses a flat rate carrying bands', function () {
    (new SaveRate())($this->tenant, new RateDefinition(
        zoneId: $this->zone('uk')->id,
        serviceLevelId: $this->serviceLevel()->id,
        rateType: RateType::Flat,
        currency: 'GBP',
        estimate: $this->estimate(),
        amountMinor: 499,
        bands: [BandDefinition::unbounded(0, 499)],
    ));
})->throws(InvalidRateDefinition::class, 'declares no bands');

it('refuses a table rate with no axis', function () {
    (new SaveRate())($this->tenant, new RateDefinition(
        zoneId: $this->zone('uk')->id,
        serviceLevelId: $this->serviceLevel()->id,
        rateType: RateType::Table,
        currency: 'GBP',
        estimate: $this->estimate(),
        bands: [BandDefinition::unbounded(0, 499)],
    ));
})->throws(InvalidRateDefinition::class, 'axis');

it('refuses a table rate whose bands do not tile the axis, at write time', function () {
    $this->tableRate($this->zone('uk'), $this->serviceLevel(), BandAxis::WeightGrams, [
        BandDefinition::bounded(0, 1_000, 399),
        BandDefinition::unbounded(2_000, 999),
    ]);
})->throws(RateBandsDoNotTileAxis::class, 'gap');

it('stores the bands of a table rate in axis order with one explicitly unbounded top', function () {
    $rate = $this->tableRate($this->zone('uk'), $this->serviceLevel(), BandAxis::WeightGrams, [
        BandDefinition::unbounded(5_000, 1_500),
        BandDefinition::bounded(0, 1_000, 399),
        BandDefinition::bounded(1_000, 5_000, 799),
    ]);

    $bands = $rate->bands()->orderBy('lower_bound')->get();

    expect($bands)->toHaveCount(3)
        ->and($bands[2]->is_unbounded)->toBeTrue()
        ->and($bands[2]->upper_bound)->toBeNull()
        ->and($bands[0]->amount_minor)->toBe(399);
});

it('replaces the band set when a rate is saved again', function () {
    $zone = $this->zone('uk');
    $service = $this->serviceLevel();
    $this->tableRate($zone, $service, BandAxis::WeightGrams, [BandDefinition::unbounded(0, 999)]);
    $rate = $this->tableRate($zone, $service, BandAxis::WeightGrams, [
        BandDefinition::bounded(0, 2_000, 499),
        BandDefinition::unbounded(2_000, 899),
    ]);

    expect($rate->bands()->count())->toBe(2);
});

it('refuses to price another tenant zone', function () {
    $zone = $this->zone('uk', tenant: 'tenant-beta');

    (new SaveRate())($this->tenant, new RateDefinition(
        zoneId: $zone->id,
        serviceLevelId: $this->serviceLevel()->id,
        rateType: RateType::Flat,
        currency: 'GBP',
        estimate: $this->estimate(),
        amountMinor: 499,
    ));
})->throws(TenantMismatch::class);

it('refuses a negative free-shipping threshold', function () {
    $this->flatRate($this->zone('uk'), $this->serviceLevel(), 499, freeAbove: -1);
})->throws(InvalidRateDefinition::class);
