<?php

declare(strict_types=1);

use Liberu\Ecommerce\Shipping\Data\Destination;
use Liberu\Ecommerce\Shipping\Data\TerritoryDefinition;
use Liberu\Ecommerce\Shipping\Exceptions\ZoneOverlapsExistingZone;
use Liberu\Ecommerce\Shipping\Models\Zone;
use Liberu\Ecommerce\Shipping\Queries\FindZoneForDestination;

it('records a zone and the destinations it covers', function () {
    $zone = $this->zone('uk-mainland', 10, [new TerritoryDefinition('GB', null, 'SW'), new TerritoryDefinition('GB', null, 'SE')]);

    expect($zone->territories)->toHaveCount(2)
        ->and($zone->precedence)->toBe(10)
        ->and($zone->tenant_id)->toBe($this->tenant);
});

it('refuses a second zone that could match the same destination at the same precedence', function () {
    $this->zone('uk', 0, [new TerritoryDefinition('GB')]);

    expect(fn () => $this->zone('uk-again', 0, [new TerritoryDefinition('GB', null, 'SW')]))
        ->toThrow(ZoneOverlapsExistingZone::class);
});

it('names both zones and the territory in the refusal', function () {
    $this->zone('uk', 0, [new TerritoryDefinition('GB')]);

    try {
        $this->zone('uk-again', 0, [new TerritoryDefinition('GB', null, 'SW')]);
    } catch (ZoneOverlapsExistingZone $exception) {
        expect($exception->zoneCode)->toBe('uk-again')
            ->and($exception->conflictingZoneCode)->toBe('uk')
            ->and($exception->precedence)->toBe(0)
            ->and($exception->getMessage())->toContain('GB/SW');

        return;
    }

    $this->fail('The overlapping zone was accepted.');
});

it('allows an overlap at a different precedence, which is how a specific zone beats a general one', function () {
    $this->zone('uk', 0, [new TerritoryDefinition('GB')]);
    $london = $this->zone('london', 10, [new TerritoryDefinition('GB', null, 'SW1')]);

    $matched = (new FindZoneForDestination())($this->tenant, new Destination('GB', null, 'SW1A 1AA'));

    expect($matched?->id)->toBe($london->id);
});

it('falls back to the lower-precedence zone outside the specific one', function () {
    $uk = $this->zone('uk', 0, [new TerritoryDefinition('GB')]);
    $this->zone('london', 10, [new TerritoryDefinition('GB', null, 'SW1')]);

    $matched = (new FindZoneForDestination())($this->tenant, new Destination('GB', null, 'M1 1AA'));

    expect($matched?->id)->toBe($uk->id);
});

it('matches nothing for a destination no zone covers', function () {
    $this->zone('uk', 0, [new TerritoryDefinition('GB')]);

    expect((new FindZoneForDestination())($this->tenant, new Destination('JP')))->toBeNull();
});

it('never matches another tenant zone', function () {
    $this->zone('uk', 0, [new TerritoryDefinition('GB')], tenant: 'tenant-beta');

    expect((new FindZoneForDestination())($this->tenant, new Destination('GB')))->toBeNull();
});

it('lets two tenants own the same territory at the same precedence', function () {
    $this->zone('uk', 0, [new TerritoryDefinition('GB')]);

    expect(fn () => $this->zone('uk', 0, [new TerritoryDefinition('GB')], tenant: 'tenant-beta'))
        ->not->toThrow(ZoneOverlapsExistingZone::class);
});

it('ignores an inactive zone when matching and when refusing an overlap', function () {
    $this->zone('retired', 0, [new TerritoryDefinition('GB')], isActive: false);
    $live = $this->zone('uk', 0, [new TerritoryDefinition('GB')]);

    expect((new FindZoneForDestination())($this->tenant, new Destination('GB'))?->id)->toBe($live->id);
});

it('replaces a zone territory set when the zone is saved again', function () {
    $zone = $this->zone('uk', 0, [new TerritoryDefinition('GB'), new TerritoryDefinition('IE')]);
    $updated = $this->zone('uk', 0, [new TerritoryDefinition('GB')]);

    expect($updated->id)->toBe($zone->id)
        ->and(Zone::query()->find($zone->id)?->territories)->toHaveCount(1);
});
