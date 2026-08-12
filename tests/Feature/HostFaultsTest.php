<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Liberu\Ecommerce\Shipping\Actions\RecordPriceAdjustment;
use Liberu\Ecommerce\Shipping\Actions\SelectShippingPrice;
use Liberu\Ecommerce\Shipping\Actions\SweepExpiredPrices;
use Liberu\Ecommerce\Shipping\Carrier\CarrierDoesNotServeDestination;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatesReturned;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingDisabled;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingUnavailable;
use Liberu\Ecommerce\Shipping\Data\CarrierRate;
use Liberu\Ecommerce\Shipping\Data\Destination;
use Liberu\Ecommerce\Shipping\Data\Money;
use Liberu\Ecommerce\Shipping\Data\Parcel;
use Liberu\Ecommerce\Shipping\Data\ParcelSet;
use Liberu\Ecommerce\Shipping\Data\TerritoryDefinition;
use Liberu\Ecommerce\Shipping\Data\TransitEstimate;
use Liberu\Ecommerce\Shipping\Enums\TransitBasis;
use Liberu\Ecommerce\Shipping\Exceptions\InvalidDestination;
use Liberu\Ecommerce\Shipping\Exceptions\InvalidParcel;
use Liberu\Ecommerce\Shipping\Exceptions\ParcelWeightMissing;
use Liberu\Ecommerce\Shipping\Exceptions\UnknownShippingPrice;
use Liberu\Ecommerce\Shipping\Models\ShippingPrice;
use Liberu\Ecommerce\Shipping\Queries\GetShippingPrice;

/*
 * One test per fault in the host this module replaces. The README names all
 * twelve; these are the proofs.
 */

/** @return list<string> */
function sourceFiles(): array
{
    $directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src'));
    $files = [];

    foreach ($directory as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = (string) file_get_contents($file->getPathname());
        }
    }

    return $files;
}

it('fault 1: a zone is representable at all', function () {
    // The host has no zone table, no zone model and no destination column: `grep
    // -rn 'shipping_zone\|ShippingZone' app/ database/` returns nothing there.
    $uk = $this->zone('uk', 0, [new TerritoryDefinition('GB')]);
    $ie = $this->zone('ie', 0, [new TerritoryDefinition('IE')]);
    $service = $this->serviceLevel();
    $this->flatRate($uk, $service, 499);
    $this->flatRate($ie, $service, 1_299);

    expect(Schema::hasTable('shipping_zones'))->toBeTrue()
        ->and(Schema::hasTable('shipping_zone_territories'))->toBeTrue()
        ->and($this->quote(destination: new Destination('GB'))->available[0]->amount->minor)->toBe(499)
        ->and($this->quote(destination: new Destination('IE'))->available[0]->amount->minor)->toBe(1_299);
});

it('fault 2: the destination decides the price instead of being thrown away', function () {
    $this->flatRate($this->zone('uk', 0, [new TerritoryDefinition('GB')]), $this->serviceLevel(), 499);

    // The host threads the address into calculateDistanceRate(), whose whole body
    // is `return 0;`. There is no distance rate here to reimplement.
    foreach (sourceFiles() as $source) {
        $lowered = strtolower($source);

        foreach (['distancerate', 'distance_rate', 'geocod', 'latitude', 'longitude', 'mileage'] as $absent) {
            expect($lowered)->not->toContain($absent);
        }
    }

    expect($this->quote(destination: new Destination('JP'))->available)->toBe([]);
});

it('fault 3: rates are integer minor units, never floats', function () {
    $this->flatRate($this->zone('uk'), $this->serviceLevel(), 499);
    $option = $this->quote()->available[0];

    expect($option->amount->minor)->toBeInt()
        ->and($option->amount)->toBeInstanceOf(Money::class)
        ->and($option->amount->decimal())->toBe('4.99')
        ->and($option->amount->jsonSerialize()['decimal'])->toBeString();

    foreach (['shipping_rates', 'shipping_rate_bands', 'shipping_prices', 'shipping_price_adjustments'] as $table) {
        foreach (Schema::getColumns($table) as $column) {
            expect($column['type_name'])->not->toBeIn(['decimal', 'numeric', 'float', 'double', 'real']);
        }
    }
});

it('fault 4: there is one weight unit, and it is grams', function () {
    // The host has products.weight with no unit, product_variants.weight_unit
    // defaulting to kg, and config('shipping.weight_unit') defaulting to oz.
    foreach (sourceFiles() as $source) {
        expect($source)->not->toContain('weight_unit');
    }

    foreach (['shipping_price_parcels', 'shipping_prices', 'shipping_rates'] as $table) {
        expect(array_column(Schema::getColumns($table), 'name'))->not->toContain('weight_unit');
    }

    expect((new ParcelSet(new Parcel(1_500)))->totalWeightGrams())->toBe(1_500);
});

it('fault 5: an estimate is an integer day range and a basis, not free text', function () {
    $this->flatRate($this->zone('uk'), $this->serviceLevel(), 499);
    $estimate = $this->quote()->available[0]->estimate;

    expect($estimate)->toBeInstanceOf(TransitEstimate::class)
        ->and($estimate->minDays)->toBeInt()
        ->and($estimate->maxDays)->toBeInt()
        ->and($estimate->basis)->toBeInstanceOf(TransitBasis::class);

    $columns = collect(Schema::getColumns('shipping_prices'))->keyBy('name');

    expect($columns['transit_min_days']['type_name'])->toBeIn(['integer', 'int', 'smallint'])
        // No date is computed anywhere: that needs a ship date, a cut-off and a
        // holiday calendar this module does not own.
        ->and($columns)->not->toHaveKey('delivery_date');
});

it('fault 6: the evidence for a charged price is never pruned', function () {
    $this->flatRate($this->zone('uk'), $this->serviceLevel(), 499);
    $reference = $this->quote()->available[0]->reference;
    app(SelectShippingPrice::class)($this->tenant, $reference);

    Carbon::setTestNow(Carbon::now()->addYear());
    $swept = (new SweepExpiredPrices())($this->tenant);
    Carbon::setTestNow();

    expect($swept)->toBe(0)
        ->and(app(GetShippingPrice::class)($this->tenant, $reference)->amount->minor)->toBe(499);
});

it('fault 7: every carrier failure mode is its own answer, not one empty array', function () {
    $this->flatRate($this->zone('uk'), $this->serviceLevel(), 499);

    $rates = new CarrierRatesReturned([new CarrierRate('acme', 'g', 'Ground', new Money(999, 'GBP'))]);

    $outcomes = [
        $this->quote()->carrierOutcome,
        $this->quote(carrier: carrierThrowing())->carrierOutcome,
        $this->quote(carrier: carrierAnswering(new CarrierDoesNotServeDestination('acme')))->carrierOutcome,
        $this->quote(carrier: carrierAnswering($rates))->carrierOutcome,
    ];

    expect($outcomes[0])->toBeInstanceOf(CarrierRatingDisabled::class)
        ->and($outcomes[1])->toBeInstanceOf(CarrierRatingUnavailable::class)
        ->and($outcomes[2])->toBeInstanceOf(CarrierDoesNotServeDestination::class)
        ->and($outcomes[3])->toBeInstanceOf(CarrierRatesReturned::class)
        ->and(array_unique(array_map(fn ($outcome): string => $outcome->code(), $outcomes)))->toHaveCount(4);
});

it('fault 8: a premium is a recorded line, never added to a stored quote', function () {
    $this->flatRate($this->zone('uk'), $this->serviceLevel(), 499);
    $reference = $this->quote()->available[0]->reference;

    $price = app(RecordPriceAdjustment::class)($this->tenant, $reference, 'drop_shipping_premium', 'Drop-shipping premium', amountMinor: 200);

    expect(ShippingPrice::query()->where('reference', $reference)->value('amount_minor'))->toBe(499)
        ->and($price->adjustments[0]->reason)->toBe('Drop-shipping premium')
        ->and($price->total()->minor)->toBe(699);
});

it('fault 9: a parcel can express a box', function () {
    $this->flatRate($this->zone('uk'), $this->serviceLevel(), 499);
    $options = $this->quote(parcels: new ParcelSet(new Parcel(1_500, 400, 300, 200)));
    $recorded = app(GetShippingPrice::class)($this->tenant, $options->available[0]->reference);

    expect($recorded->parcels[0]->lengthMm)->toBe(400)
        ->and($recorded->parcels[0]->widthMm)->toBe(300)
        ->and($recorded->parcels[0]->heightMm)->toBe(200)
        ->and($recorded->parcels[0]->longestSideMm())->toBe(400);
});

it('fault 10: a missing weight is refused, not silently zero', function () {
    expect(fn () => Parcel::fromNullableWeight(null))->toThrow(ParcelWeightMissing::class)
        ->and(fn () => new Parcel(0))->toThrow(InvalidParcel::class);

    foreach (Schema::getColumns('shipping_price_parcels') as $column) {
        if ($column['name'] === 'weight_grams') {
            expect($column['nullable'])->toBeFalse()
                ->and($column['default'])->toBeNull();
        }
    }
});

it('fault 11: there is no address-verification stub pointing at a placeholder host', function () {
    foreach (sourceFiles() as $source) {
        expect($source)->not->toContain('address-verifier')
            ->and($source)->not->toContain('Http::')
            ->and($source)->not->toContain('verifyAddress');
    }

    // What the module does instead: it validates the destination it is given,
    // locally, and refuses one it cannot use.
    expect(fn () => new Destination('United Kingdom'))->toThrow(InvalidDestination::class);
});

it('fault 12: who may spend a price is the tenant, not a client-adjacent string', function () {
    $this->flatRate($this->zone('uk'), $this->serviceLevel(), 499);
    $reference = $this->quote()->available[0]->reference;

    $columns = array_column(Schema::getColumns('shipping_prices'), 'name');

    expect($columns)->toContain('tenant_id')
        ->and($columns)->not->toContain('session_id')
        ->and($columns)->not->toContain('user_id')
        ->and(fn () => app(SelectShippingPrice::class)('tenant-beta', $reference))
        ->toThrow(UnknownShippingPrice::class);
});
