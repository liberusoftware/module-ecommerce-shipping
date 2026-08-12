<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Liberu\Ecommerce\Shipping\Contracts\FetchesCarrierRates;
use Liberu\Ecommerce\Shipping\Contracts\ResolvesParcels;

$tables = [
    'shipping_zones',
    'shipping_zone_territories',
    'shipping_service_levels',
    'shipping_rates',
    'shipping_rate_bands',
    'shipping_restrictions',
    'shipping_prices',
    'shipping_price_parcels',
    'shipping_price_adjustments',
    'shipping_idempotency_keys',
];

it('ships every table it says it does', function (string $table) {
    expect(Schema::hasTable($table))->toBeTrue();
})->with($tables);

it('carries a non-nullable tenant_id on every table', function (string $table) {
    $column = collect(Schema::getColumns($table))->firstWhere('name', 'tenant_id');

    expect($column)->not->toBeNull()
        ->and($column['nullable'])->toBeFalse();
})->with($tables);

it('stores no money, weight, dimension or rate in a decimal, float or double column', function (string $table) {
    foreach (Schema::getColumns($table) as $column) {
        expect($column['type_name'])->not->toBeIn(['decimal', 'numeric', 'float', 'double', 'real']);
    }
})->with($tables);

it('stores every money, weight and dimension column as an integer', function () {
    $integerColumns = [
        'shipping_rates' => ['amount_minor', 'free_above_subtotal_minor', 'transit_min_days', 'transit_max_days'],
        'shipping_rate_bands' => ['lower_bound', 'upper_bound', 'amount_minor'],
        'shipping_restrictions' => ['threshold'],
        'shipping_prices' => ['amount_minor', 'subtotal_minor', 'item_count', 'transit_min_days', 'transit_max_days'],
        'shipping_price_parcels' => ['weight_grams', 'length_mm', 'width_mm', 'height_mm'],
        'shipping_price_adjustments' => ['amount_minor', 'basis_points'],
    ];

    foreach ($integerColumns as $table => $columns) {
        $found = collect(Schema::getColumns($table))->keyBy('name');

        foreach ($columns as $column) {
            expect($found)->toHaveKey($column)
                ->and($found[$column]['type_name'])->toBeIn(['integer', 'int', 'bigint', 'smallint']);
        }
    }
});

it('carries no weight_unit column anywhere, because a unit column is the fault', function (string $table) {
    $names = array_column(Schema::getColumns($table), 'name');

    expect($names)->not->toContain('weight_unit');
})->with($tables);

it('holds no pre-summed shipping total column anywhere', function (string $table) {
    foreach (array_column(Schema::getColumns($table), 'name') as $name) {
        expect($name)->not->toBe('shipping_total')
            ->and($name)->not->toBe('total_minor');
    }
})->with($tables);

it('declares the foreign keys its rules depend on', function () {
    // Asserted as declared keys rather than by inserting an orphan: SQLite only
    // enforces them with the pragma on, and a pragma set inside RefreshDatabase's
    // transaction is a no-op.
    $expected = [
        'shipping_zone_territories' => 'shipping_zones',
        'shipping_rates' => 'shipping_zones',
        'shipping_rate_bands' => 'shipping_rates',
        'shipping_price_parcels' => 'shipping_prices',
        'shipping_price_adjustments' => 'shipping_prices',
    ];

    foreach ($expected as $table => $parent) {
        $foreign = collect(Schema::getForeignKeys($table))->pluck('foreign_table');

        expect($foreign)->toContain($parent);
    }
});

it('deliberately keeps recorded prices free of foreign keys to the rules that produced them', function () {
    // A price is evidence and must outlive the rules it came from, so deleting a
    // zone must not cascade a recorded price away.
    expect(Schema::getForeignKeys('shipping_prices'))->toBe([]);
});

it('has a unique index that makes an idempotency key mean one thing per tenant', function () {
    $columns = collect(Schema::getIndexes('shipping_idempotency_keys'))
        ->filter(fn (array $index): bool => $index['unique'])
        ->pluck('columns')
        ->all();

    expect($columns)->toContain(['tenant_id', 'operation', 'idempotency_key']);
});

it('boots with no bindings for either seam, because unbound means different things', function () {
    expect(app()->bound(FetchesCarrierRates::class))->toBeFalse()
        ->and(app()->bound(ResolvesParcels::class))->toBeFalse()
        ->and(DB::connection()->getDriverName())->toBe('sqlite');
});
