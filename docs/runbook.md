# Runbook

## Sweeping expired offers

Quoting records one price row per option offered, so the table grows with
traffic. `SweepExpiredPrices` removes offers that expired and were never taken:

```php
app(Liberu\Ecommerce\Shipping\Actions\SweepExpiredPrices::class)($tenantId);
```

Schedule it per tenant, hourly or daily. It excludes selected prices in the
query itself, so it cannot delete the evidence for something that was charged —
that is the host fault it exists not to repeat. It emits
`ExpiredShippingPricesSwept` with the count.

Do **not** write a variant that sweeps on `expires_at` alone.

## A carrier is down

`CarrierRatingDegraded` fires only when a *bound* carrier threw, timed out or
answered with something that was not a rate. Live rating being switched off
emits nothing, because that is a configuration.

Alert on the rate of `CarrierRatingDegraded`, not on its presence: one failure is
weather, a sustained rate is an outage. While it is degraded, buyers still see
derived rates and the surfaces show an explicit degraded notice; nothing silently
bills a different price.

`CarrierDoesNotServeDestination` is not an incident. It is a working carrier
saying it does not go there.

## "This order was charged a shipping price that looks wrong"

1. Read the price back: `GetShippingPrice($tenantId, $reference)`.
2. Look at `kind`.
   - **Quoted**: the provenance names the carrier, the service, the upstream
     rate identifier and the instant. That is the whole answer — the number
     cannot be recomputed and was never meant to be. Take it to the carrier.
   - **Derived**: the provenance names the zone, the rate and the band. Re-run
     `QuoteShippingOptions` with the destination, parcels, subtotal and item
     count recorded on the row and you will get the same integer, unless a rule
     has been edited since.
3. Check the adjustment lines. A total that differs from the price is the sum of
   the price line and its adjustments, each with its own reason code — that is
   the fold, and there is no stored total for it to disagree with.

## An operator cannot save a zone

`ZoneOverlapsExistingZone` names both zones and the precedence. Either give one
of them a different precedence — higher wins, so a specific zone beats a general
one — or narrow its territories. This is refused at write time on purpose:
two zones that could match the same address at the same precedence make the
resulting price a coin toss nobody can audit afterwards.

## An operator cannot save a rate

`RateBandsDoNotTileAxis` says exactly which invariant broke: bands must start at
zero, meet edge to edge with no gap and no overlap, and end in exactly one band
declared unbounded. `InvalidRateDefinition` covers a flat rate with no amount, a
flat rate carrying bands, and a table rate with no axis.

## A buyer sees no shipping options

Read `ShippingOptions::$outcome` before anything else:

- `no_zone_matched` — no zone covers that destination. Author one, or accept
  that you do not ship there.
- `no_rates_configured` — a zone matched but nothing is priced in it. An
  operator has half-finished a setup.
- `all_excluded` — every service level was excluded, and `excluded` carries the
  reason for each. Show the reason; that is what it is for.
- `options_available` — there are options; the surface is at fault, not the
  domain.

## Idempotency

`IdempotencyKeyConflict` is permanent: the same key was used with a different
payload, and retrying will never succeed. Answer 409 and mint nothing.

`IdempotencyKeyInFlight` is transient: same key, same payload, still running.
Answer 423 with `Retry-After` and let the caller retry with the *same* key.

Tell them apart with `instanceof`. Never by reading a message.

## Expired offers at checkout

`ShippingPriceExpired` means the buyer took too long. Refuse, re-quote, and let
them choose again at the new price. Do not silently re-quote behind them: they
agreed to a number, and charging a different one is the fault the refusal
exists to prevent.
