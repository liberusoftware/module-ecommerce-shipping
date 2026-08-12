# Adopting this module

## 1. Install and enable

```json
"repositories": [
  { "type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-shipping" }
]
```

Composer honours `repositories` only from the root manifest, so the **host** must
carry that entry; a package requiring this one cannot supply it for you.

```bash
composer require liberusoftware/ecommerce-shipping
```

Nothing boots on install. `extra.laravel.providers` is empty by design; the host
enables the module by name through `MODULES_ENABLED`, which is what registers
`Liberu\Ecommerce\Shipping\ShippingServiceProvider`.

## 2. Bind the seams

`ResolvesParcels` has **no default binding and must be bound**. This module never
looks a product weight up — that belongs to `ecommerce-catalog`, and importing it
would cross the boundary the package exists to hold. An unbound resolver fails
loudly with `ParcelResolverNotBound`; a resolver that answers null for a basket
that exists raises `ParcelsNotResolved`, which is a different condition.

Your implementation must not invent a weight. Build parcels through
`Parcel::fromNullableWeight()` so an unweighed product raises
`ParcelWeightMissing` instead of contributing nothing to the box.

`FetchesCarrierRates` is optional. **Leaving it unbound is a supported
deployment**, not a fault: the store prices from its own rate tables and every
surface says so plainly. Bind it only when you have a carrier to bind.

## 3. The host tables this replaces

This module ships its own tables under the `shipping_` prefix and **adopts
neither host table**, because both are wrong in ways that matter:

| Host table | Why it is not adopted |
| --- | --- |
| `shipping_methods` | `decimal(8,2)` rates cast to float, a free-text `estimated_delivery_time`, and no destination column of any kind. The replacement is `shipping_service_levels` plus `shipping_zones` and `shipping_rates`. |
| `shipping_quotes` | `decimal(10,2)` amount re-cast `(float)` at two edges, no tenant or site column, an authorisation predicate over `session_id`/`user_id`, and a pruner that deletes the evidence for a charged price. The replacement is `shipping_prices`. |

The names deliberately do not collide, so both schemas can sit in one database
while you migrate. When you are done:

1. Stop writing to `shipping_methods` and `shipping_quotes`.
2. Delete `app/Console/Commands/PruneShippingQuotes.php` and its schedule entry.
   The sweep here refuses to touch a selected price; that pruner did not.
3. Drop `orders.shipping_quote_id`'s `nullOnDelete()` behaviour by pointing the
   order at a `shipping_prices.reference` instead. A reference to a selected
   price never dangles, because a selected price is never deleted.
4. Drop the host tables once nothing reads them.

**Do not migrate the old data.** A `decimal(10,2)` amount can be converted to
minor units by string arithmetic, but the provenance a quoted price needs —
which carrier, which service, which upstream rate identifier, at what instant,
against which parcel — was never recorded, so an imported row would claim an
evidential quality it does not have. Import old rows, if you must, as
historical records outside this module.

## 4. The distance rate is deleted, not reimplemented

`ShippingService::calculateDistanceRate()` in the host accepts an address and
returns `0`. There is no equivalent here and there will not be one: this module
computes no distance, geocodes nothing, holds no coordinates and has no mileage
rate. A zone is a set of destination predicates — country, subdivision,
postcode prefix — and that is the whole vocabulary.

If your store genuinely prices by distance, that is a carrier's job: implement
`FetchesCarrierRates` against something that can answer it, and the answer will
be recorded as a quoted price with its provenance, which is what it is.

Delete `calculateDistanceRate()`, and delete the `$address` parameter from any
signature that only ever decorated it.

## 5. Free shipping: the threshold form moves here, the coupon form does not

The host implements free shipping twice, and the two do not agree with each
other; nothing reconciles them today:

- `customer_groups.free_shipping_threshold`, a `decimal(10,2)` on a customer
  group.
- `discounts.type` carrying a `'free_shipping'` enum case.

**The threshold form moves here.** "Shipping costs zero in this zone above this
order subtotal" is a row in a rate table, so it is
`shipping_rates.free_above_subtotal_minor`, in integer minor units, and the
recorded price says `applied_rule = free_threshold` when it applies.

**The coupon form does not move here.** "This coupon makes shipping free" is a
promotion, and it belongs to `ecommerce-promotions` (#895, unbuilt). It composes
by adjusting the total, not by changing what shipping costs — and when it is
built, it will record its effect as its own line rather than editing a shipping
price, for the same reason a surcharge does.

Until that module exists, leave `discounts.type = 'free_shipping'` where it is
and do not reimplement it here. If you need the customer-group threshold to keep
working during the migration, express it as a rate rule per zone; a threshold
that varies by customer group is a promotion, not a rate.

## 6. Surcharges

Anything the host added to a price after the fact — the drop-shipping premium in
`CheckoutController`, a handling fee, a fuel uplift — becomes a call to
`RecordPriceAdjustment` with its own reason code. The price row is never edited,
and the charge is the fold of the price line and its adjustments. If you find
yourself wanting to write a total to a column, that is the fault this module
replaces.
