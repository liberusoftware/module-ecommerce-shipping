<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Carrier;

/**
 * What happened when live rating was attempted.
 *
 * Four cases, told apart by type, because the host's single empty array made
 * "live rating is off", "the carrier is down", "the carrier does not serve this
 * address" and "here are the rates" indistinguishable — and then billed a
 * different price in three of the four.
 */
interface CarrierRatingOutcome
{
    /** A stable machine key for a transport or a template to switch on. */
    public function code(): string;
}
