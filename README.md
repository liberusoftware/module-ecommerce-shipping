# Ecommerce: Shipping Core Module

> This package is the authoritative, provider-neutral implementation of Shipping. It owns domain behavior and data; optional API, Filament, Livewire, React, Vue, and Nuxt packages translate its public contracts for their surfaces.

[Software](https://liberusoftware.com) ·
[Hosting](https://liberuhosting.com) ·
[Services](https://liberuservices.com) ·
[Liberu Group](https://liberugroup.com)

![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white) ![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
[![Latest release](https://img.shields.io/github/v/release/liberusoftware/module-ecommerce-shipping?sort=semver)](https://github.com/liberusoftware/module-ecommerce-shipping/releases/latest) [![Tests](https://github.com/liberusoftware/module-ecommerce-shipping/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/liberusoftware/module-ecommerce-shipping/actions/workflows/tests.yml)

## What this owns

**Shipping owns what a shipment costs and how long it is expected to take. It
owns no order, no parcel's contents, no label, and no package in motion.**

Draw the line at the moment of purchase: shipping answers *what will this cost
and how long will it take*, and everything after the buyer says yes — labels,
manifests, tracking numbers, carrier pickups, package contents, shipment status
— is somebody else's.

It imports no sibling module. Catalog owns the product and its weight, so this
module is **told** its parcels and never looks a weight up. Tax owns whether
shipping is taxable, so this module emits a price and never computes tax.
Pricing and promotions own coupons, so "this coupon makes shipping free" is not
here — see `docs/adoption.md`.

**It does not compute a delivery date.** A date needs a ship date, a cut-off
time and a holiday calendar this module does not own. It publishes an integer
transit-day range and the basis those days are counted on, and stops there.

## Features

- Destination zones with an explicit precedence, and an ambiguous overlap
  refused at write time rather than resolved by a read-time sort.
- Flat and table rates in integer minor units, with bands that must tile their
  axis and an explicitly declared unbounded top band.
- Free shipping above an order subtotal, as a rate rule.
- Restrictions that exclude a service level *with the reason*, so a destination
  with nothing available is an explicit outcome and not an empty list.
- A carrier rating seam with four distinguishable outcomes, including "live
  rating is switched off", which is a configuration and not an error.
- Derived and quoted prices told apart by a stored discriminator, with a quoted
  price kept verbatim and never recomputed, adjusted or pruned once selected.
- Surcharges recorded as their own lines: the charge for a shipment is a fold,
  and no column anywhere holds a pre-summed total.

## A price is one of two different things

- A **derived** price is computed here from rules this module holds — a zone
  matched, a rate row applied, a weight band, a free-shipping threshold. It is
  reproducible from recorded rules, and `tests/Feature/ProofTest.php` reproduces
  every one of them with the carrier seam ripped out.
- A **quoted** price is an answer a third party gave at an instant about a
  future physical movement. It is **irreproducible**: ask again in a minute and
  the number may differ. So it is stored verbatim with its provenance and
  survives every rule in this module being deleted — also proved there.

## The two seams

| Contract | Unbound means | Behaviour |
| --- | --- | --- |
| `FetchesCarrierRates` | live rating is off for this deployment | derived rates only, stated plainly, no error |
| `ResolvesParcels` | a deployment fault | fails loudly at the boundary |

`FetchesCarrierRates` answers with one of four types, never a bare list:

| Outcome | Means |
| --- | --- |
| `CarrierRatingDisabled` | nothing is bound; live rating is off |
| `CarrierRatingUnavailable` | a bound carrier threw, timed out or answered with nonsense |
| `CarrierDoesNotServeDestination` | the carrier answered, and its answer was no |
| `CarrierRatesReturned` | at least one rate, never constructible empty |

## What this replaces

Twelve faults in the host application (`liberu-ecommerce/ecommerce-laravel`).
Each is named here with the test that proves it gone. All twelve tests live in
`tests/Feature/HostFaultsTest.php` unless another file is named.

| # | Host fault | Proof |
| --- | --- | --- |
| 1 | A zone is unrepresentable. `shipping_methods` is `name, description, base_rate, weight_rate, max_weight, estimated_delivery_time, is_active` and nothing else; there is no destination column and no zone table anywhere in `app/` or `database/`. | *fault 1: a zone is representable at all* |
| 2 | The destination is accepted and thrown away. `ShippingService::calculateShippingCost($method, $cart, $address)` threads the address into `calculateDistanceRate()`, whose whole body is `return 0;`, and `isMethodAvailable()` ignores it too. | *fault 2: the destination decides the price instead of being thrown away* |
| 3 | Rates are floats. `base_rate` and `weight_rate` are `decimal(8,2)` cast to `float`; `shipping_quotes.amount` is re-cast `(float)` at the JSON edge and again at checkout. | *fault 3: rates are integer minor units, never floats* |
| 4 | Three weight units and no agreement between them: `products.weight` has no unit column, `product_variants.weight_unit` defaults to `kg`, `config('shipping.weight_unit')` defaults to `oz`, and `EasyPostCarrier::parcel()` multiplies by 16 only when the config says `lb`. | *fault 4: there is one weight unit, and it is grams* |
| 5 | `estimated_delivery_time` is free text, validated only as `required|string|max:255`. | *fault 5: an estimate is an integer day range and a basis, not free text* |
| 6 | The evidence for a charged price is deleted on a schedule: `PruneShippingQuotes` deletes on `expires_at` alone, and `orders.shipping_quote_id` is nulled on delete — for a number the migration's own docblock says cannot be recomputed. | *fault 6: the evidence for a charged price is never pruned* |
| 7 | Every failure mode is the same empty array: a missing API key, a non-2xx response, any `Throwable`, and no carrier configured all return `[]`, and checkout silently bills a flat rate instead. | *fault 7: every carrier failure mode is its own answer, not one empty array* |
| 8 | A config float is added to an authoritative stored quote: `round((float) $quote->amount + $premium, 2)`, leaving `orders.shipping_cost` equal to no quote anyone ever fetched. | *fault 8: a premium is a recorded line, never added to a stored quote* |
| 9 | Parcels have no dimensions. `getLiveRates()` builds `['weight' => …]` and `EasyPostCarrier::parcel()` `array_filter`s the dimensions out because no caller supplies them. | *fault 9: a parcel can express a box* |
| 10 | A missing weight is silently zero: `(float) ($item['weight'] ?? $weights[$productId] ?? 0)`, over a `decimal(8,2)->default(0)` column. | *fault 10: a missing weight is refused, not silently zero* |
| 11 | `verifyAddress()` calls `https://api.address-verifier.com` with a key defined nowhere, from a method with no caller. | *fault 11: there is no address-verification stub pointing at a placeholder host* |
| 12 | A quote's authorisation predicate is `session_id = $sessionId` **OR** `user_id = $userId`, with `''` passed by one caller and the literal `'api'` passed for every headless buyer. There is no tenant column at all. | *fault 12: who may spend a price is the tenant, not a client-adjacent string* |

Two things the host got right are kept: persisting a fetched carrier rate and
billing the stored amount, and falling back to flat rates when live rating is
unavailable. Fault 7 is that the fallback was silent and undifferentiated, not
that it exists.

## Requirements

- **PHP 8.5**
- **Composer 2**
- A supported database (e.g. MySQL, PostgreSQL, SQLite)

## Quick start

```bash
composer require liberusoftware/ecommerce-shipping
```

The package registers nothing on install: `extra.laravel.providers` is empty by
design, and the host enables the module through `MODULES_ENABLED`. Bind
`ResolvesParcels` before quoting anything — see `docs/adoption.md`.

## Documentation

- [`docs/domain.md`](docs/domain.md) — the model, the public surface, and the known limits.
- [`docs/adoption.md`](docs/adoption.md) — migrating a host off the tables and behaviour this replaces.
- [`docs/runbook.md`](docs/runbook.md) — operating it: sweeping, degraded carriers, and what to do when a price looks wrong.
- [Liberu Main Documentation](https://github.com/liberusoftware/documentation)
- [Architecture & Standards Index](https://github.com/liberusoftware/documentation/tree/main/architecture)

## Related Liberu Projects

| Project | Repository | Purpose |
| --- | --- | --- |
| **Boilerplate** | [liberusoftware/boilerplate-laravel](https://github.com/liberusoftware/boilerplate-laravel) | Shared Laravel application foundation and reference composition |
| **CMS** | [liberu-cms/cms-laravel](https://github.com/liberu-cms/cms-laravel) | Structured content, publishing, media, multisite, and headless delivery |
| **CRM** | [liberu-crm/crm-laravel](https://github.com/liberu-crm/crm-laravel) | Customer data, sales, marketing, service, and customer success |
| **Billing** | [liberu-billing/billing-laravel](https://github.com/liberu-billing/billing-laravel) | Products, subscriptions, invoicing, payments, and provisioning |
| **Accounting** | [liberu-accounting/accounting-laravel](https://github.com/liberu-accounting/accounting-laravel) | Ledgers, banking, tax, expenses, close, and financial reporting |
| **Ecommerce** | [liberu-ecommerce/ecommerce-laravel](https://github.com/liberu-ecommerce/ecommerce-laravel) | Catalog, checkout, orders, fulfillment, returns, B2B, and omnichannel commerce |
| **Control Panel** | [liberu-control-panel/control-panel-laravel](https://github.com/liberu-control-panel/control-panel-laravel) | Hosting, infrastructure, DNS, mail, databases, backups, and security operations |
| **Automation** | [liberu-automation/automation-laravel](https://github.com/liberu-automation/automation-laravel) | Governed workflows, provider-neutral AI, approvals, and connectors |

## Security

Please do not report security vulnerabilities through public GitHub issues.
Follow our [Security Policy](https://github.com/liberusoftware/documentation/blob/main/architecture/SECURITY.md) for private reporting and supported versions.

## License

This project is open-source software. You may use, modify, and distribute it
under the terms described in [LICENSE.md](LICENSE.md).

The linked license text is authoritative; this summary is not legal advice.

## Feedback and contributing

Feedback and contributions are welcome. You can help by reporting reproducible
bugs, proposing focused enhancements, improving documentation or translations,
and submitting tested code changes.

Before contributing, please read [CONTRIBUTING.md](https://github.com/liberusoftware/documentation/blob/main/standards/CONTRIBUTING.md) and our
[Code of Conduct](https://github.com/liberusoftware/documentation/blob/main/architecture/CODE_OF_CONDUCT.md). Search existing issues first, then use
the appropriate issue template. Pull requests should explain the problem and
approach, remain focused, include or update tests, pass the required workflows,
and document user-visible or breaking changes.

## Contributors

Thank you to everyone who helps improve Liberu.

<a href="https://github.com/liberusoftware/module-ecommerce-shipping/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=liberusoftware/module-ecommerce-shipping" alt="Contributors to liberusoftware/module-ecommerce-shipping">
</a>

[View the full contributors graph](https://github.com/liberusoftware/module-ecommerce-shipping/graphs/contributors).
