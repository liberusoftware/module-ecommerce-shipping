<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatesReturned;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingDisabled;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingOutcome;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingUnavailable;
use Liberu\Ecommerce\Shipping\Contracts\FetchesCarrierRates;
use Liberu\Ecommerce\Shipping\Data\CarrierRate;
use Liberu\Ecommerce\Shipping\Data\Destination;
use Liberu\Ecommerce\Shipping\Data\ExcludedOption;
use Liberu\Ecommerce\Shipping\Data\Money;
use Liberu\Ecommerce\Shipping\Data\ParcelSet;
use Liberu\Ecommerce\Shipping\Data\ShippingOption;
use Liberu\Ecommerce\Shipping\Data\ShippingOptions;
use Liberu\Ecommerce\Shipping\Enums\AppliedRule;
use Liberu\Ecommerce\Shipping\Enums\BandAxis;
use Liberu\Ecommerce\Shipping\Enums\PriceKind;
use Liberu\Ecommerce\Shipping\Enums\PriceStatus;
use Liberu\Ecommerce\Shipping\Enums\QuoteOutcome;
use Liberu\Ecommerce\Shipping\Enums\RateType;
use Liberu\Ecommerce\Shipping\Enums\RestrictionType;
use Liberu\Ecommerce\Shipping\Events\CarrierRatingDegraded;
use Liberu\Ecommerce\Shipping\Events\ShippingOptionsQuoted;
use Liberu\Ecommerce\Shipping\Exceptions\InvalidMoney;
use Liberu\Ecommerce\Shipping\Exceptions\InvalidRateDefinition;
use Liberu\Ecommerce\Shipping\Exceptions\QuoteInputMissing;
use Liberu\Ecommerce\Shipping\Exceptions\RateBandsDoNotTileAxis;
use Liberu\Ecommerce\Shipping\Models\Rate;
use Liberu\Ecommerce\Shipping\Models\Restriction;
use Liberu\Ecommerce\Shipping\Models\ShippingPrice;
use Liberu\Ecommerce\Shipping\Models\Zone;
use Liberu\Ecommerce\Shipping\Queries\FindZoneForDestination;
use Liberu\Ecommerce\Shipping\Support\Reference;
use Throwable;

/**
 * Prices a destination and a parcel set, and records every price it offers.
 *
 * Derived prices come from this module's own rules and are reproducible from
 * them. Quoted prices come from a carrier and are stored verbatim with their
 * provenance, because nothing recorded here could ever recompute one.
 *
 * The carrier seam being unbound is a configuration, not a failure: the outcome
 * says which of the four things happened, and no caller has to guess.
 */
final class QuoteShippingOptions
{
    public function __construct(
        private FindZoneForDestination $zones,
        private ?FetchesCarrierRates $carrier = null,
        private int $offerTtlMinutes = 30,
    ) {}

    public function __invoke(
        string $tenantId,
        Destination $destination,
        ParcelSet $parcels,
        string $currency,
        ?int $subtotalMinor = null,
        ?int $itemCount = null,
    ): ShippingOptions {
        $currency = strtoupper($currency);
        $expiresAt = Carbon::now()->addMinutes($this->offerTtlMinutes);
        $carrierOutcome = $this->rateWithCarrier($tenantId, $destination, $parcels);

        $zone = ($this->zones)($tenantId, $destination);
        $available = [];
        $excluded = [];

        if ($zone instanceof Zone) {
            [$available, $excluded] = $this->deriveOptions($tenantId, $zone, $destination, $parcels, $currency, $subtotalMinor, $itemCount, $expiresAt);
        }

        if ($carrierOutcome instanceof CarrierRatesReturned) {
            foreach ($carrierOutcome->rates as $rate) {
                $available[] = $this->recordQuoted($tenantId, $rate, $destination, $parcels, $currency, $subtotalMinor, $itemCount, $expiresAt);
            }
        }

        $options = new ShippingOptions(
            $available,
            $excluded,
            $carrierOutcome,
            $this->outcome($available, $excluded, $zone),
            $zone?->code,
        );

        Event::dispatch(new ShippingOptionsQuoted($tenantId, $destination, $options));

        return $options;
    }

    /**
     * @param  list<ShippingOption>  $available
     * @param  list<ExcludedOption>  $excluded
     */
    private function outcome(array $available, array $excluded, ?Zone $zone): QuoteOutcome
    {
        return match (true) {
            $available !== [] => QuoteOutcome::OptionsAvailable,
            $excluded !== [] => QuoteOutcome::AllExcluded,
            ! $zone instanceof Zone => QuoteOutcome::NoZoneMatched,
            default => QuoteOutcome::NoRatesConfigured,
        };
    }

    /**
     * Four outcomes, one type each. A carrier that is switched off, a carrier
     * that is down, and a carrier that does not go there are three different
     * answers, and the host's single empty array was all three at once.
     */
    private function rateWithCarrier(string $tenantId, Destination $destination, ParcelSet $parcels): CarrierRatingOutcome
    {
        if (! $this->carrier instanceof FetchesCarrierRates) {
            return new CarrierRatingDisabled();
        }

        try {
            $outcome = $this->carrier->fetch($tenantId, $destination, $parcels);
        } catch (Throwable $exception) {
            $outcome = CarrierRatingUnavailable::from($this->carrier::class, $exception);
        }

        if ($outcome instanceof CarrierRatingUnavailable) {
            Event::dispatch(new CarrierRatingDegraded($tenantId, $outcome));
        }

        return $outcome;
    }

    /**
     * @return array{0: list<ShippingOption>, 1: list<ExcludedOption>}
     */
    private function deriveOptions(
        string $tenantId,
        Zone $zone,
        Destination $destination,
        ParcelSet $parcels,
        string $currency,
        ?int $subtotalMinor,
        ?int $itemCount,
        Carbon $expiresAt,
    ): array {
        $restrictions = Restriction::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('zone_id')->orWhere('zone_id', $zone->id))
            ->get();

        $rates = Rate::query()
            ->with(['bands', 'serviceLevel'])
            ->where('tenant_id', $tenantId)
            ->where('zone_id', $zone->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $available = [];
        $excluded = [];

        foreach ($rates as $rate) {
            $serviceLevel = $rate->serviceLevel;

            if (! $serviceLevel->is_active) {
                continue;
            }

            $blocking = $this->firstBlocking($restrictions, $rate, $parcels, $subtotalMinor);

            if ($blocking instanceof Restriction) {
                $excluded[] = new ExcludedOption(
                    $serviceLevel->code,
                    $serviceLevel->name,
                    $blocking->restriction_type,
                    $blocking->reason_code,
                    $blocking->reason,
                );

                continue;
            }

            if ($rate->currency !== $currency) {
                throw InvalidMoney::mismatch($rate->currency, $currency);
            }

            [$amountMinor, $rule, $bandId] = $this->priceFrom($rate, $parcels, $subtotalMinor, $itemCount);

            $price = $this->record($tenantId, [
                'kind' => PriceKind::Derived,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'transit_min_days' => $rate->transit_min_days,
                'transit_max_days' => $rate->transit_max_days,
                'transit_basis' => $rate->transit_basis,
                'service_level_code' => $serviceLevel->code,
                'service_level_name' => $serviceLevel->name,
                'zone_id' => $zone->id,
                'zone_code' => $zone->code,
                'rate_id' => $rate->id,
                'rate_band_id' => $bandId,
                'applied_rule' => $rule,
            ], $destination, $parcels, $subtotalMinor, $itemCount, $expiresAt);

            $available[] = new ShippingOption(
                $price->reference,
                $serviceLevel->code,
                $serviceLevel->name,
                new Money($amountMinor, $currency),
                $rate->estimate(),
                PriceKind::Derived,
                $rule,
            );
        }

        return [$available, $excluded];
    }

    private function recordQuoted(
        string $tenantId,
        CarrierRate $rate,
        Destination $destination,
        ParcelSet $parcels,
        string $currency,
        ?int $subtotalMinor,
        ?int $itemCount,
        Carbon $expiresAt,
    ): ShippingOption {
        if ($rate->amount->currency !== $currency) {
            throw InvalidMoney::mismatch($rate->amount->currency, $currency);
        }

        $price = $this->record($tenantId, [
            'kind' => PriceKind::Quoted,
            'amount_minor' => $rate->amount->minor,
            'currency' => $currency,
            'transit_min_days' => $rate->estimate?->minDays,
            'transit_max_days' => $rate->estimate?->maxDays,
            'transit_basis' => $rate->estimate?->basis,
            'service_level_code' => $rate->serviceCode,
            'service_level_name' => $rate->serviceName,
            'carrier_code' => $rate->carrierCode,
            'carrier_service_code' => $rate->serviceCode,
            'carrier_rate_reference' => $rate->rateReference,
            'quoted_at' => Carbon::now(),
            'applied_rule' => AppliedRule::CarrierQuote,
        ], $destination, $parcels, $subtotalMinor, $itemCount, $expiresAt);

        return new ShippingOption(
            $price->reference,
            $rate->serviceCode,
            $rate->serviceName,
            $rate->amount,
            $rate->estimate,
            PriceKind::Quoted,
            AppliedRule::CarrierQuote,
            $rate->carrierCode,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function record(
        string $tenantId,
        array $attributes,
        Destination $destination,
        ParcelSet $parcels,
        ?int $subtotalMinor,
        ?int $itemCount,
        Carbon $expiresAt,
    ): ShippingPrice {
        $price = ShippingPrice::query()->create([
            ...$attributes,
            'tenant_id' => $tenantId,
            'reference' => Reference::mint(),
            'status' => PriceStatus::Offered,
            'destination_country' => $destination->countryCode,
            'destination_subdivision' => $destination->subdivisionCode,
            'destination_postcode' => $destination->postcode,
            'subtotal_minor' => $subtotalMinor,
            'item_count' => $itemCount,
            'expires_at' => $expiresAt,
        ]);

        foreach ($parcels->parcels as $parcel) {
            $price->parcels()->create([
                'tenant_id' => $tenantId,
                'weight_grams' => $parcel->weightGrams,
                'length_mm' => $parcel->lengthMm,
                'width_mm' => $parcel->widthMm,
                'height_mm' => $parcel->heightMm,
            ]);
        }

        return $price;
    }

    /**
     * @param  Collection<int, Restriction>|\Illuminate\Database\Eloquent\Collection<int, Restriction>  $restrictions
     */
    private function firstBlocking(iterable $restrictions, Rate $rate, ParcelSet $parcels, ?int $subtotalMinor): ?Restriction
    {
        foreach ($restrictions as $restriction) {
            if ($restriction->service_level_id !== null && $restriction->service_level_id !== $rate->service_level_id) {
                continue;
            }

            $threshold = (int) $restriction->threshold;
            $longestSide = $parcels->longestSideMm();

            $blocks = match ($restriction->restriction_type) {
                RestrictionType::MaxWeightGrams => $parcels->totalWeightGrams() > $threshold,
                RestrictionType::MaxDimensionMm => $longestSide !== null && $longestSide > $threshold,
                RestrictionType::MinSubtotalMinor => $subtotalMinor === null || $subtotalMinor < $threshold,
                RestrictionType::DestinationExcluded => true,
            };

            if ($blocks) {
                return $restriction;
            }
        }

        return null;
    }

    /**
     * @return array{0: int, 1: AppliedRule, 2: int|null}
     */
    private function priceFrom(Rate $rate, ParcelSet $parcels, ?int $subtotalMinor, ?int $itemCount): array
    {
        if ($rate->free_above_subtotal_minor !== null) {
            if ($subtotalMinor === null) {
                throw QuoteInputMissing::subtotal('free_above_subtotal_minor');
            }

            if ($subtotalMinor >= $rate->free_above_subtotal_minor) {
                return [0, AppliedRule::FreeThreshold, null];
            }
        }

        if ($rate->rate_type === RateType::Flat) {
            return [(int) $rate->amount_minor, AppliedRule::Flat, null];
        }

        if (! $rate->band_axis instanceof BandAxis) {
            throw InvalidRateDefinition::tableRateNeedsAxis();
        }

        $value = match ($rate->band_axis) {
            BandAxis::WeightGrams => $parcels->totalWeightGrams(),
            BandAxis::SubtotalMinor => $subtotalMinor ?? throw QuoteInputMissing::subtotal('table rate on subtotal_minor'),
            BandAxis::ItemCount => $itemCount ?? throw QuoteInputMissing::itemCount(),
        };

        foreach ($rate->bands as $band) {
            if ($band->toDefinition()->contains($value)) {
                return [$band->amount_minor, AppliedRule::TableBand, $band->id];
            }
        }

        throw RateBandsDoNotTileAxis::gap($rate->band_axis, $value, $value);
    }
}
