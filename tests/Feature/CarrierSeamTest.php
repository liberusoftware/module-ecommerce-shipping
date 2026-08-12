<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\Shipping\Carrier\CarrierDoesNotServeDestination;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingDisabled;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingOutcome;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingUnavailable;
use Liberu\Ecommerce\Shipping\Contracts\FetchesCarrierRates;
use Liberu\Ecommerce\Shipping\Data\Destination;
use Liberu\Ecommerce\Shipping\Data\ParcelSet;
use Liberu\Ecommerce\Shipping\Enums\AppliedRule;
use Liberu\Ecommerce\Shipping\Enums\PriceKind;
use Liberu\Ecommerce\Shipping\Enums\QuoteOutcome;
use Liberu\Ecommerce\Shipping\Events\CarrierRatingDegraded;
use Liberu\Ecommerce\Shipping\Models\ShippingPrice;

it('treats an unbound carrier as live rating being off, not as an error', function () {
    $this->flatRate($this->zone('uk'), $this->serviceLevel(), 499);

    $options = $this->quote();

    expect($options->carrierOutcome)->toBeInstanceOf(CarrierRatingDisabled::class)
        ->and($options->carrierOutcome->code())->toBe('carrier_rating_disabled')
        ->and($options->available)->toHaveCount(1)
        ->and($options->outcome)->toBe(QuoteOutcome::OptionsAvailable);
});

it('tells a carrier being down apart from a carrier being off', function () {
    $this->flatRate($this->zone('uk'), $this->serviceLevel(), 499);

    $options = $this->quote(carrier: carrierThrowing());

    expect($options->carrierOutcome)->toBeInstanceOf(CarrierRatingUnavailable::class)
        ->and($options->carrierOutcome->code())->toBe('carrier_unavailable')
        ->and($options->available)->toHaveCount(1);
});

it('records the reason a carrier was unavailable rather than swallowing the throwable', function () {
    $this->flatRate($this->zone('uk'), $this->serviceLevel(), 499);

    $outcome = $this->quote(carrier: carrierThrowing())->carrierOutcome;

    expect($outcome)->toBeInstanceOf(CarrierRatingUnavailable::class)
        ->and($outcome->reason)->toContain('connect timeout after 15s');
});

it('publishes a degraded event only when a bound carrier failed', function () {
    Event::fake();
    $this->flatRate($this->zone('uk'), $this->serviceLevel(), 499);

    $this->quote();
    Event::assertNotDispatched(CarrierRatingDegraded::class);

    $this->quote(carrier: carrierThrowing());
    Event::assertDispatched(CarrierRatingDegraded::class);
});

it('tells a carrier that does not serve the destination apart from a carrier that is down', function () {
    $this->flatRate($this->zone('uk'), $this->serviceLevel(), 499);

    $options = $this->quote(carrier: carrierAnswering(new CarrierDoesNotServeDestination('acme')));

    expect($options->carrierOutcome)->toBeInstanceOf(CarrierDoesNotServeDestination::class)
        ->and($options->carrierOutcome->code())->toBe('carrier_does_not_serve_destination')
        ->and($options->available)->toHaveCount(1);
});

it('offers carrier rates beside derived ones and marks them quoted', function () {
    $this->flatRate($this->zone('uk'), $this->serviceLevel(), 499);

    $options = $this->quote(carrier: carrierAnswering(acmeRate()));
    $quoted = $options->available[1];

    expect($options->available)->toHaveCount(2)
        ->and($quoted->kind)->toBe(PriceKind::Quoted)
        ->and($quoted->appliedRule)->toBe(AppliedRule::CarrierQuote)
        ->and($quoted->carrierCode)->toBe('acme')
        ->and($quoted->amount->minor)->toBe(1_234)
        ->and($quoted->estimate?->describe())->toBe('1 business days');
});

it('stores a quoted price verbatim with the provenance that cannot be recomputed', function () {
    $options = $this->quote(carrier: carrierAnswering(acmeRate()));
    $price = ShippingPrice::query()->where('reference', $options->available[0]->reference)->firstOrFail();

    expect($price->kind)->toBe(PriceKind::Quoted)
        ->and($price->amount_minor)->toBe(1_234)
        ->and($price->carrier_code)->toBe('acme')
        ->and($price->carrier_service_code)->toBe('acme_next_day')
        ->and($price->carrier_rate_reference)->toBe('rate_9f2c')
        ->and($price->quoted_at)->not->toBeNull()
        ->and($price->zone_id)->toBeNull()
        ->and($price->rate_id)->toBeNull()
        ->and($price->parcels)->toHaveCount(1);
});

it('quotes a carrier even where no zone of its own matches', function () {
    $options = $this->quote(destination: new Destination('JP'), carrier: carrierAnswering(acmeRate()));

    expect($options->outcome)->toBe(QuoteOutcome::OptionsAvailable)
        ->and($options->available)->toHaveCount(1)
        ->and($options->available[0]->kind)->toBe(PriceKind::Quoted);
});

it('is told the parcel and never looks a weight up', function () {
    $carrier = new class() implements FetchesCarrierRates
    {
        public ?int $seen = null;

        public function fetch(string $tenantId, Destination $destination, ParcelSet $parcels): CarrierRatingOutcome
        {
            $this->seen = $parcels->totalWeightGrams();

            return new CarrierDoesNotServeDestination('acme');
        }
    };

    $this->quote(parcels: $this->parcels(1_750), carrier: $carrier);

    expect($carrier->seen)->toBe(1_750);
});
