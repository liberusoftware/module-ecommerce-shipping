<?php

declare(strict_types=1);

use Liberu\Ecommerce\Shipping\Carrier\CarrierDoesNotServeDestination;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatesReturned;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingDisabled;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingUnavailable;
use Liberu\Ecommerce\Shipping\Data\CarrierRate;
use Liberu\Ecommerce\Shipping\Data\Money;
use Liberu\Ecommerce\Shipping\Data\TransitEstimate;
use Liberu\Ecommerce\Shipping\Enums\TransitBasis;

it('gives every carrier outcome its own type and code', function () {
    $codes = [
        (new CarrierRatingDisabled())->code(),
        (new CarrierRatingUnavailable('acme', 'timeout'))->code(),
        (new CarrierDoesNotServeDestination('acme'))->code(),
        (new CarrierRatesReturned([new CarrierRate('acme', 'ground', 'Ground', new Money(499, 'GBP'))]))->code(),
    ];

    expect($codes)->toHaveCount(4)
        ->and(array_unique($codes))->toHaveCount(4);
});

it('refuses to represent an empty answer as a rate list', function () {
    new CarrierRatesReturned([]);
})->throws(InvalidArgumentException::class, 'CarrierDoesNotServeDestination');

it('records what a carrier failure actually was', function () {
    $outcome = CarrierRatingUnavailable::from('acme', new RuntimeException('connect timeout'));

    expect($outcome->carrierCode)->toBe('acme')
        ->and($outcome->reason)->toContain('connect timeout');
});

it('describes a transit estimate without inventing a date', function () {
    expect((new TransitEstimate(2, 4, TransitBasis::BusinessDays))->describe())->toBe('2-4 business days')
        ->and((new TransitEstimate(1, 1, TransitBasis::CalendarDays))->describe())->toBe('1 calendar days');
});

it('refuses a transit range that is not a range', function () {
    new TransitEstimate(5, 2, TransitBasis::BusinessDays);
})->throws(InvalidArgumentException::class);
