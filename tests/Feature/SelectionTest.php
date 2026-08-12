<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\Shipping\Actions\SelectShippingPrice;
use Liberu\Ecommerce\Shipping\Actions\SweepExpiredPrices;
use Liberu\Ecommerce\Shipping\Enums\PriceStatus;
use Liberu\Ecommerce\Shipping\Events\ShippingPriceSelected;
use Liberu\Ecommerce\Shipping\Exceptions\IdempotencyKeyConflict;
use Liberu\Ecommerce\Shipping\Exceptions\IdempotencyKeyInFlight;
use Liberu\Ecommerce\Shipping\Exceptions\ShippingPriceExpired;
use Liberu\Ecommerce\Shipping\Exceptions\ShippingPriceImmutable;
use Liberu\Ecommerce\Shipping\Exceptions\UnknownShippingPrice;
use Liberu\Ecommerce\Shipping\Models\IdempotencyKey;
use Liberu\Ecommerce\Shipping\Models\ShippingPrice;
use Liberu\Ecommerce\Shipping\Queries\GetShippingPrice;
use Liberu\Ecommerce\Shipping\Support\IdempotencyGuard;

beforeEach(function () {
    $this->flatRate($this->zone('uk'), $this->serviceLevel(), 499);
    $this->select = app(SelectShippingPrice::class);
});

it('marks an offered price as the one that priced something', function () {
    $reference = $this->quote()->available[0]->reference;

    $selected = ($this->select)($this->tenant, $reference);

    expect($selected->status)->toBe(PriceStatus::Selected)
        ->and($selected->selectedAt)->not->toBeNull()
        ->and($selected->amount->minor)->toBe(499);
});

it('publishes the selection', function () {
    Event::fake();
    $reference = $this->quote()->available[0]->reference;

    ($this->select)($this->tenant, $reference);

    Event::assertDispatched(ShippingPriceSelected::class, fn (ShippingPriceSelected $event): bool => $event->reference === $reference && $event->amountMinor === 499);
});

it('refuses an expired offer rather than quietly re-quoting it', function () {
    $reference = $this->quote()->available[0]->reference;
    Carbon::setTestNow(Carbon::now()->addHours(2));

    expect(fn () => ($this->select)($this->tenant, $reference))
        ->toThrow(ShippingPriceExpired::class, 'Ask for options again');

    Carbon::setTestNow();
});

it('refuses a price this tenant does not own', function () {
    $reference = $this->quote()->available[0]->reference;

    ($this->select)('tenant-beta', $reference);
})->throws(UnknownShippingPrice::class);

it('selects the same price twice without complaining', function () {
    $reference = $this->quote()->available[0]->reference;

    ($this->select)($this->tenant, $reference);

    expect(($this->select)($this->tenant, $reference)->status)->toBe(PriceStatus::Selected);
});

it('replays a selection made under the same idempotency key', function () {
    $reference = $this->quote()->available[0]->reference;

    ($this->select)($this->tenant, $reference, 'key-1');
    ($this->select)($this->tenant, $reference, 'key-1');

    expect(IdempotencyKey::query()->count())->toBe(1);
});

it('refuses the same key for a different price, permanently', function () {
    $options = $this->quote();
    ($this->select)($this->tenant, $options->available[0]->reference, 'key-1');

    ($this->select)($this->tenant, $this->quote()->available[0]->reference, 'key-1');
})->throws(IdempotencyKeyConflict::class);

it('reports an in-flight duplicate as its own, transient class', function () {
    $reference = $this->quote()->available[0]->reference;
    IdempotencyKey::query()->create([
        'tenant_id' => $this->tenant,
        'operation' => SelectShippingPrice::OPERATION,
        'idempotency_key' => 'key-1',
        'payload_hash' => IdempotencyGuard::hash(['reference' => $reference]),
        'state' => IdempotencyKey::STATE_IN_FLIGHT,
    ]);

    expect(fn () => ($this->select)($this->tenant, $reference, 'key-1'))
        ->toThrow(IdempotencyKeyInFlight::class);
});

it('gives a caller a retry hint on the transient case and none on the permanent one', function () {
    $inFlight = IdempotencyKeyInFlight::key('key-1');

    expect($inFlight->retryAfterSeconds)->toBeGreaterThan(0)
        ->and($inFlight)->not->toBeInstanceOf(IdempotencyKeyConflict::class);
});

it('refuses to edit a selected price through the model', function () {
    $reference = $this->quote()->available[0]->reference;
    ($this->select)($this->tenant, $reference);

    $price = ShippingPrice::query()->where('reference', $reference)->firstOrFail();

    expect(fn () => $price->update(['amount_minor' => 1]))->toThrow(ShippingPriceImmutable::class);
});

it('never sweeps a selected price, even long after it would have expired', function () {
    $options = $this->quote();
    ($this->select)($this->tenant, $options->available[0]->reference);
    $survivor = $options->available[0]->reference;

    $untouched = $this->quote()->available[0]->reference;
    Carbon::setTestNow(Carbon::now()->addDays(30));

    $swept = (new SweepExpiredPrices())($this->tenant);
    Carbon::setTestNow();

    expect($swept)->toBe(1)
        ->and(ShippingPrice::query()->where('reference', $untouched)->exists())->toBeFalse()
        ->and(app(GetShippingPrice::class)($this->tenant, $survivor)->reference)->toBe($survivor);
});

it('leaves an unexpired offer alone when sweeping', function () {
    $this->quote();

    expect((new SweepExpiredPrices())($this->tenant))->toBe(0);
});

it('refuses to read a price that does not exist', function () {
    app(GetShippingPrice::class)($this->tenant, 'shp_nothing');
})->throws(UnknownShippingPrice::class);
