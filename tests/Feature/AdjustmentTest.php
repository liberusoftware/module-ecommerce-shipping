<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\Shipping\Actions\RecordPriceAdjustment;
use Liberu\Ecommerce\Shipping\Actions\SelectShippingPrice;
use Liberu\Ecommerce\Shipping\Events\ShippingPriceAdjusted;
use Liberu\Ecommerce\Shipping\Exceptions\IdempotencyKeyConflict;
use Liberu\Ecommerce\Shipping\Exceptions\UnknownShippingPrice;
use Liberu\Ecommerce\Shipping\Models\PriceAdjustment;
use Liberu\Ecommerce\Shipping\Models\ShippingPrice;
use Liberu\Ecommerce\Shipping\Queries\TotalShippingCharge;

beforeEach(function () {
    $this->flatRate($this->zone('uk'), $this->serviceLevel(), 499);
    $this->adjust = app(RecordPriceAdjustment::class);
    $this->reference = $this->quote()->available[0]->reference;
});

it('records a surcharge as its own line and leaves the price alone', function () {
    $adjusted = ($this->adjust)($this->tenant, $this->reference, 'drop_shipping_premium', 'Drop-shipping premium', amountMinor: 200);

    expect($adjusted->amount->minor)->toBe(499)
        ->and($adjusted->adjustments)->toHaveCount(1)
        ->and($adjusted->adjustments[0]->amount->minor)->toBe(200)
        ->and($adjusted->adjustments[0]->reasonCode)->toBe('drop_shipping_premium')
        ->and($adjusted->total()->minor)->toBe(699)
        ->and(ShippingPrice::query()->where('reference', $this->reference)->value('amount_minor'))->toBe(499);
});

it('computes a percentage surcharge in integer basis points', function () {
    $adjusted = ($this->adjust)($this->tenant, $this->reference, 'fuel', 'Fuel surcharge', basisPoints: 350);

    // 3.5% of 499 is 17.465 minor units; intdiv keeps it whole.
    expect($adjusted->adjustments[0]->amount->minor)->toBe(17)
        ->and($adjusted->adjustments[0]->basisPoints)->toBe(350)
        ->and($adjusted->total()->minor)->toBe(516);
});

it('records a reduction as a line too, so the sum is always a fold', function () {
    ($this->adjust)($this->tenant, $this->reference, 'goodwill', 'Goodwill reduction', amountMinor: -99);

    expect(app(TotalShippingCharge::class)($this->tenant, $this->reference)->minor)->toBe(400);
});

it('gives the same total whatever order the lines are replayed in', function () {
    foreach ([['a', 200], ['b', -50], ['c', 17]] as [$code, $minor]) {
        ($this->adjust)($this->tenant, $this->reference, $code, 'Line '.$code, amountMinor: $minor);
    }

    $total = app(TotalShippingCharge::class)($this->tenant, $this->reference)->minor;

    // Reverse the recorded order and fold again: integers, so the sum cannot drift.
    $lines = PriceAdjustment::query()->orderByDesc('id')->pluck('amount_minor')->all();
    $replayed = array_reduce($lines, static fn (int $carry, int $minor): int => $carry + $minor, 499);

    expect($total)->toBe(666)->and($replayed)->toBe($total);
});

it('adjusts a price after it was selected, because that is when a surcharge appears', function () {
    app(SelectShippingPrice::class)($this->tenant, $this->reference);

    expect(($this->adjust)($this->tenant, $this->reference, 'handling', 'Handling', amountMinor: 150)->total()->minor)->toBe(649);
});

it('publishes each adjustment with its reason', function () {
    Event::fake();

    ($this->adjust)($this->tenant, $this->reference, 'handling', 'Handling', amountMinor: 150);

    Event::assertDispatched(ShippingPriceAdjusted::class, fn (ShippingPriceAdjusted $event): bool => $event->reasonCode === 'handling' && $event->amountMinor === 150);
});

it('replays an adjustment under the same key instead of charging twice', function () {
    ($this->adjust)($this->tenant, $this->reference, 'handling', 'Handling', amountMinor: 150, idempotencyKey: 'key-1');
    $second = ($this->adjust)($this->tenant, $this->reference, 'handling', 'Handling', amountMinor: 150, idempotencyKey: 'key-1');

    expect($second->adjustments)->toHaveCount(1);
});

it('refuses the same key for a different adjustment', function () {
    ($this->adjust)($this->tenant, $this->reference, 'handling', 'Handling', amountMinor: 150, idempotencyKey: 'key-1');

    ($this->adjust)($this->tenant, $this->reference, 'handling', 'Handling', amountMinor: 999, idempotencyKey: 'key-1');
})->throws(IdempotencyKeyConflict::class);

it('refuses to adjust another tenant price', function () {
    ($this->adjust)('tenant-beta', $this->reference, 'handling', 'Handling', amountMinor: 150);
})->throws(UnknownShippingPrice::class);
