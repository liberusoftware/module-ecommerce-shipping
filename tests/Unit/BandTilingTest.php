<?php

declare(strict_types=1);

use Liberu\Ecommerce\Shipping\Data\BandDefinition;
use Liberu\Ecommerce\Shipping\Enums\BandAxis;
use Liberu\Ecommerce\Shipping\Exceptions\RateBandsDoNotTileAxis;
use Liberu\Ecommerce\Shipping\Support\BandTiling;

$axis = BandAxis::WeightGrams;

it('accepts bands that tile the axis from zero to an unbounded top', function () use ($axis) {
    $ordered = BandTiling::assertTiles($axis, [
        BandDefinition::unbounded(5_000, 1_500),
        BandDefinition::bounded(0, 1_000, 399),
        BandDefinition::bounded(1_000, 5_000, 799),
    ]);

    expect($ordered)->toHaveCount(3)
        ->and($ordered[0]->lowerBound)->toBe(0)
        ->and($ordered[2]->isUnbounded)->toBeTrue();
});

it('refuses a band set with no bands', function () use ($axis) {
    BandTiling::assertTiles($axis, []);
})->throws(RateBandsDoNotTileAxis::class, 'declares no bands');

it('refuses bands that do not start at zero', function () use ($axis) {
    BandTiling::assertTiles($axis, [BandDefinition::bounded(100, 1_000, 399), BandDefinition::unbounded(1_000, 999)]);
})->throws(RateBandsDoNotTileAxis::class, 'must tile the axis from 0');

it('refuses a gap', function () use ($axis) {
    BandTiling::assertTiles($axis, [BandDefinition::bounded(0, 1_000, 399), BandDefinition::unbounded(2_000, 999)]);
})->throws(RateBandsDoNotTileAxis::class, 'leave a gap between 1000 and 2000');

it('refuses an overlap', function () use ($axis) {
    BandTiling::assertTiles($axis, [BandDefinition::bounded(0, 1_000, 399), BandDefinition::unbounded(500, 999)]);
})->throws(RateBandsDoNotTileAxis::class, 'overlap');

it('refuses a band set whose top band is bounded', function () use ($axis) {
    BandTiling::assertTiles($axis, [BandDefinition::bounded(0, 1_000, 399), BandDefinition::bounded(1_000, 5_000, 999)]);
})->throws(RateBandsDoNotTileAxis::class, 'declare no unbounded top band');

it('refuses an unbounded band that is not the top one', function () use ($axis) {
    BandTiling::assertTiles($axis, [BandDefinition::unbounded(0, 399), BandDefinition::unbounded(1_000, 999)]);
})->throws(RateBandsDoNotTileAxis::class, 'more than one unbounded band');

it('refuses a band that contains nothing', function () use ($axis) {
    BandTiling::assertTiles($axis, [BandDefinition::bounded(0, 0, 399), BandDefinition::unbounded(0, 999)]);
})->throws(RateBandsDoNotTileAxis::class, 'contains nothing');

it('picks the band a value falls in, half-open at the upper bound', function () {
    $bands = [
        BandDefinition::bounded(0, 1_000, 399),
        BandDefinition::bounded(1_000, 5_000, 799),
        BandDefinition::unbounded(5_000, 1_500),
    ];

    expect(BandTiling::pick($bands, 0)?->amountMinor)->toBe(399)
        ->and(BandTiling::pick($bands, 999)?->amountMinor)->toBe(399)
        ->and(BandTiling::pick($bands, 1_000)?->amountMinor)->toBe(799)
        ->and(BandTiling::pick($bands, 5_000)?->amountMinor)->toBe(1_500)
        ->and(BandTiling::pick($bands, 500_000)?->amountMinor)->toBe(1_500)
        ->and(BandTiling::pick($bands, -1))->toBeNull();
});
