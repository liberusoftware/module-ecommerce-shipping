<?php

declare(strict_types=1);

use Liberu\Ecommerce\Shipping\Data\Destination;
use Liberu\Ecommerce\Shipping\Data\TerritoryDefinition;
use Liberu\Ecommerce\Shipping\Exceptions\InvalidDestination;

it('normalises a destination', function () {
    $destination = new Destination(' gb ', 'eng', 'sw1a 1aa');

    expect($destination->countryCode)->toBe('GB')
        ->and($destination->subdivisionCode)->toBe('ENG')
        ->and($destination->postcode)->toBe('SW1A1AA')
        ->and($destination->describe())->toBe('GB ENG SW1A1AA');
});

it('refuses a destination that is not a country code', function (string $given) {
    new Destination($given);
})->with(['', 'GBR', 'United Kingdom', '1'])->throws(InvalidDestination::class);

it('treats an empty subdivision or postcode as absent', function () {
    $destination = new Destination('GB', '  ', '');

    expect($destination->subdivisionCode)->toBeNull()
        ->and($destination->postcode)->toBeNull();
});

it('matches a destination against a territory', function (TerritoryDefinition $territory, Destination $destination, bool $matches) {
    expect($territory->matches($destination))->toBe($matches);
})->with([
    'country only' => [new TerritoryDefinition('GB'), new Destination('GB', null, 'SW1A1AA'), true],
    'other country' => [new TerritoryDefinition('GB'), new Destination('FR'), false],
    'subdivision matches' => [new TerritoryDefinition('GB', 'SCT'), new Destination('GB', 'SCT'), true],
    'subdivision differs' => [new TerritoryDefinition('GB', 'SCT'), new Destination('GB', 'ENG'), false],
    'postcode prefix matches' => [new TerritoryDefinition('GB', null, 'SW'), new Destination('GB', null, 'SW1A 1AA'), true],
    'postcode prefix differs' => [new TerritoryDefinition('GB', null, 'BT'), new Destination('GB', null, 'SW1A1AA'), false],
    'postcode required but absent' => [new TerritoryDefinition('GB', null, 'SW'), new Destination('GB'), false],
]);

it('decides overlap without sampling addresses', function (TerritoryDefinition $left, TerritoryDefinition $right, bool $overlaps) {
    expect($left->overlaps($right))->toBe($overlaps)
        ->and($right->overlaps($left))->toBe($overlaps);
})->with([
    'same country' => [new TerritoryDefinition('GB'), new TerritoryDefinition('GB'), true],
    'different countries' => [new TerritoryDefinition('GB'), new TerritoryDefinition('IE'), false],
    'country contains subdivision' => [new TerritoryDefinition('GB'), new TerritoryDefinition('GB', 'SCT'), true],
    'different subdivisions' => [new TerritoryDefinition('GB', 'SCT'), new TerritoryDefinition('GB', 'WLS'), false],
    'nested postcode prefixes' => [new TerritoryDefinition('GB', null, 'SW'), new TerritoryDefinition('GB', null, 'SW1'), true],
    'sibling postcode prefixes' => [new TerritoryDefinition('GB', null, 'SW'), new TerritoryDefinition('GB', null, 'SE'), false],
    'country wildcard beats a prefix' => [new TerritoryDefinition('GB'), new TerritoryDefinition('GB', null, 'BT'), true],
]);
