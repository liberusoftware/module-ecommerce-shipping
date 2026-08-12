<?php

declare(strict_types=1);

use Liberu\Ecommerce\Shipping\Data\Parcel;
use Liberu\Ecommerce\Shipping\Data\ParcelSet;
use Liberu\Ecommerce\Shipping\Exceptions\InvalidParcel;
use Liberu\Ecommerce\Shipping\Exceptions\ParcelWeightMissing;

it('refuses a parcel with no weight instead of defaulting it to zero', function () {
    Parcel::fromNullableWeight(null, reference: 'sku-9');
})->throws(ParcelWeightMissing::class, 'Parcel [sku-9] was presented with no weight');

it('accepts a parcel whose weight is known', function () {
    expect(Parcel::fromNullableWeight(750)->weightGrams)->toBe(750);
});

it('refuses a weight that is not positive', function (int $grams) {
    new Parcel($grams);
})->with([0, -1])->throws(InvalidParcel::class);

it('refuses a partially dimensioned box', function () {
    new Parcel(500, 300, 200);
})->throws(InvalidParcel::class, 'some dimensions but not others');

it('refuses a dimension that is not positive', function () {
    new Parcel(500, 300, 200, 0);
})->throws(InvalidParcel::class);

it('carries dimensions in millimetres and reports the longest side', function () {
    $parcel = new Parcel(500, 300, 200, 150);

    expect($parcel->hasDimensions())->toBeTrue()
        ->and($parcel->longestSideMm())->toBe(300)
        ->and((new Parcel(500))->longestSideMm())->toBeNull();
});

it('sums a parcel set in grams', function () {
    $set = new ParcelSet(new Parcel(500, 300, 200, 150), new Parcel(1_250));

    expect($set->totalWeightGrams())->toBe(1_750)
        ->and($set->count())->toBe(2)
        ->and($set->longestSideMm())->toBe(300);
});

it('refuses an empty parcel set', function () {
    new ParcelSet();
})->throws(InvalidParcel::class);
