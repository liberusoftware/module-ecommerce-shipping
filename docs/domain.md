# The shipping domain

> Shipping owns what a shipment costs and how long it is expected to take. It
> owns no order, no parcel's contents, no label, and no package in motion.

## The one idea everything else follows from

A shipping price is one of two different kinds of thing, and confusing them is
the defect this module exists to prevent.

A **derived** price is computed here from rules this module holds: a zone
matched, a rate row applied, a weight band, a free-shipping threshold. It is
reproducible from recorded rules for as long as those rules are recorded.

A **quoted** price is an answer a third party gave at an instant, about a future
physical movement, in response to a question only they can answer. Nothing this
module records will ever let it recompute one. So a quoted price is stored
verbatim with its provenance — which carrier, which service, which upstream rate
identifier, at what instant, against which parcel and which destination — and is
never recomputed, never adjusted, and never pruned once selected.

Every recorded price carries which kind it is in a stored column. It is never
inferred from whether a carrier column happens to be null.

## Tables

All ten carry a non-nullable, indexed `tenant_id`.

| Table | Holds |
| --- | --- |
| `shipping_zones` | a named zone with an integer precedence |
| `shipping_zone_territories` | the destination predicates a zone matches on |
| `shipping_service_levels` | a named way of shipping — what the host called a shipping method |
| `shipping_rates` | one service level priced in one zone, with its transit estimate |
| `shipping_rate_bands` | the half-open bands of a table rate |
| `shipping_restrictions` | a rule that excludes a service level, with its reason |
| `shipping_prices` | a recorded price: derived or quoted, offered or selected |
| `shipping_price_parcels` | the parcels a price was quoted against, in grams and millimetres |
| `shipping_price_adjustments` | surcharges and reductions, each with its own reason |
| `shipping_idempotency_keys` | the two-class idempotency ledger |

There is no `decimal`, `float` or `double` column in any of them; money is
integer minor units, weight is integer grams, dimensions are integer
millimetres, and percentages are integer basis points. There is no
`weight_unit` column, and no column anywhere holds a pre-summed total.

`shipping_prices` deliberately declares **no** foreign key to a zone or a rate.
A recorded price is evidence and must outlive the rules it came from, so
deleting a zone must not cascade a price away. It keeps `zone_code` as a
snapshot for the same reason.

## Zones and precedence

A zone is a set of destination predicates — country, optional subdivision,
optional postcode prefix. It is never a radius: this module computes no
distance, geocodes nothing and holds no coordinates.

Precedence is an integer and **higher wins**. Two *active* zones that could
match the same destination at the same precedence are refused when the second
one is saved, with `ZoneOverlapsExistingZone` naming both zones and the
territory. Overlap at different precedences is legitimate and is how a specific
zone beats a general one. Ordering resolved at read time is ordering nobody can
audit, so it is resolved at write time or not at all.

Two territories overlap when their countries match, their subdivisions match or
either is a wildcard, and their postcode prefixes are wildcards or one is a
prefix of the other. That is decided arithmetically, not by sampling addresses.

## Rates and bands

A rate prices one service level in one zone, as `flat` or `table`, and carries
the transit estimate for that pairing. A table rate declares the axis its bands
are looked up on — `weight_grams`, `subtotal_minor` or `item_count` — and its
bands are half-open `[lower, upper)`. They must tile the axis from zero with
exactly one top band that says `is_unbounded` explicitly; a null upper bound
does not imply it. A gap, an overlap, an empty band or a missing unbounded top
is refused when the rate is written, with `RateBandsDoNotTileAxis`.

Free shipping above an order subtotal is `free_above_subtotal_minor` on the rate
— a rate rule, not a discount. When it applies, the recorded price says so:
`applied_rule` is `free_threshold`, not `flat`.

An input the rules need and the caller did not supply is refused with
`QuoteInputMissing` rather than defaulted. A missing subtotal is not zero.

## Restrictions

A restriction excludes a service level and comes back with the reason it did.
Nothing is silently filtered: `ShippingOptions` carries `available` and
`excluded` side by side, and the `QuoteOutcome` enum names the four distinct
outcomes — `options_available`, `all_excluded`, `no_zone_matched` and
`no_rates_configured`. A blank list is never how a surface learns something went
wrong.

## Estimates

An estimate is an integer transit-day range plus its basis, `business_days` or
`calendar_days`. **The module does not compute a delivery date**, because that
needs a ship date, a cut-off time and a holiday calendar it does not own.

## The public surface

Presentation packages code against these and nothing else.

**Contracts** — `FetchesCarrierRates`, `ResolvesParcels`.

**Carrier outcomes** — `CarrierRatingOutcome` and its four implementations
`CarrierRatingDisabled`, `CarrierRatingUnavailable`,
`CarrierDoesNotServeDestination`, `CarrierRatesReturned`.

**Actions** — `QuoteShippingOptions`, `ResolveParcels`, `SelectShippingPrice`,
`RecordPriceAdjustment`, `SweepExpiredPrices`, `SaveZone`, `SaveRate`,
`SaveRestriction`.

**Queries** — `FindZoneForDestination`, `GetShippingPrice`,
`TotalShippingCharge`.

**Data** — `Money`, `Destination`, `Parcel`, `ParcelSet`, `TransitEstimate`,
`CarrierRate`, `ShippingOption`, `ExcludedOption`, `ShippingOptions`,
`RecordedPrice`, `RecordedAdjustment`, `PriceProvenance` with
`DerivedProvenance` and `QuotedProvenance`, and the authoring shapes
`ZoneDefinition`, `TerritoryDefinition`, `RateDefinition`, `BandDefinition`,
`RestrictionDefinition`.

**Enums** — `PriceKind`, `PriceStatus`, `RateType`, `BandAxis`, `TransitBasis`,
`RestrictionType`, `AppliedRule`, `QuoteOutcome`.

**Events** — `ShippingOptionsQuoted`, `ShippingPriceSelected`,
`ShippingPriceAdjusted`, `CarrierRatingDegraded`, `ExpiredShippingPricesSwept`.

**Exceptions** — all extend `ShippingException`:
`ZoneOverlapsExistingZone`, `RateBandsDoNotTileAxis`, `InvalidRateDefinition`,
`QuoteInputMissing`, `InvalidDestination`, `InvalidMoney`, `InvalidParcel`,
`ParcelWeightMissing`, `ParcelResolverNotBound`, `ParcelsNotResolved`,
`ShippingPriceExpired`, `ShippingPriceImmutable`, `UnknownShippingPrice`,
`IdempotencyKeyConflict`, `IdempotencyKeyInFlight`, `TenantMismatch`.

Tell `IdempotencyKeyConflict` (permanent — answer 409) and
`IdempotencyKeyInFlight` (transient — answer 423 with `Retry-After`) apart by
`instanceof`. They are opposite instructions to a caller and must never be
distinguished by decoding a message.

## Known limits

These are on the record rather than hidden.

1. **`tenant_id` has no contract across the fleet.** The domain takes it as an
   explicit argument on every entry point; `-api` reads it off the actor;
   `-filament` uses `Filament::getTenant()`. This is the fleet's fourth
   implementation of one idea and the inconsistency is real. It is not settled
   here, and no fifth answer is invented here either.
2. **A price's immutability is enforced in a model hook and in the actions, not
   by the database.** Model events do not fire for `query()->update()` or
   `query()->delete()`, so code that bypasses the model can still edit a
   selected price. The sweep is safe regardless, because it excludes selected
   rows in the query itself rather than relying on a hook.
3. **A quoted price cannot be proved by reproduction.** The three-way proof
   proves the partition, the reproducibility of derived prices, and the survival
   of quoted ones — but no test can recompute a carrier's answer, and any design
   claiming otherwise is lying about the world.
4. **No delivery date, ever.** See above. A consumer that needs one must own the
   ship date, the cut-off and the calendar.
5. **Zone matching is linear over a tenant's active zones.** It loads them with
   their territories and walks them in precedence order. That is fine for the
   hundreds a store has and would not be fine for millions.
6. **A quote records a price row per offered option**, so quoting is a write.
   Unselected offers expire and are swept; see `runbook.md`.
7. **Currency mismatches refuse rather than convert.** A rate or a carrier rate
   in a currency other than the one asked for raises `InvalidMoney`; this module
   holds no exchange rates and will not invent one.
