# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this package
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-08-12

### Added

- Destination zones with an explicit integer precedence, and territories
  matched on country, subdivision and postcode prefix. An ambiguous overlap
  between two active zones at the same precedence is refused when the second
  one is saved, naming both zones.
- Service levels, and rates that price one service level in one zone: flat or
  table, in integer minor units, with an integer transit-day estimate and an
  explicit `business_days` / `calendar_days` basis.
- Table rates over declared bands on weight in grams, order subtotal in minor
  units, or item count. A band set that leaves a gap, overlaps, or declares no
  explicitly unbounded top band is refused at write time.
- Free shipping above an order subtotal, as a rate rule rather than a discount.
- Restrictions that exclude a service level and come back with the reason they
  excluded it, instead of silently shortening the list.
- Recorded shipping prices carrying a stored `derived` / `quoted` discriminator,
  their provenance, the destination and the parcels they were priced against.
- `FetchesCarrierRates`: a live-rating seam whose absence is a supported
  deployment, with four distinguishable outcomes.
- `ResolvesParcels`: a seam with no default binding, whose absence is a fault.
- Adjustments recorded as their own lines with their own reasons; the charge for
  a shipment is a fold over its price line and those lines.
- Selection with two-class idempotency, expiry that refuses rather than
  re-quotes, and a sweep that never touches a selected price.

[0.1.0]: https://github.com/liberusoftware/module-ecommerce-shipping/releases/tag/0.1.0
