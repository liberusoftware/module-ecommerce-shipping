<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Contracts;

use Liberu\Ecommerce\Shipping\Carrier\CarrierDoesNotServeDestination;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingOutcome;
use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingUnavailable;
use Liberu\Ecommerce\Shipping\Data\Destination;
use Liberu\Ecommerce\Shipping\Data\ParcelSet;

/**
 * The live-rating seam. Declared here, implemented by an adapter package.
 *
 * **Leaving it unbound is a valid deployment.** The module then prices from its
 * own rate tables and says so; it does not report an error.
 *
 * An implementation returns one of the outcome types and never a bare list. If
 * it throws instead, the caller records the throw as
 * {@see CarrierRatingUnavailable} — but a
 * carrier that simply has nothing for a destination must say so with
 * {@see CarrierDoesNotServeDestination},
 * because "no service here" and "we are down" are different answers to the
 * buyer.
 *
 * An implementation converts to whatever units its carrier speaks *inside
 * itself*, from the grams and millimetres it is given here.
 */
interface FetchesCarrierRates
{
    public function fetch(string $tenantId, Destination $destination, ParcelSet $parcels): CarrierRatingOutcome;
}
