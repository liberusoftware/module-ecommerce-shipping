<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Enums;

/**
 * A destination with nothing available is a distinct, explicit outcome — never
 * an empty list that a surface can mistake for a loading state.
 */
enum QuoteOutcome: string
{
    case OptionsAvailable = 'options_available';
    case AllExcluded = 'all_excluded';
    case NoZoneMatched = 'no_zone_matched';
    case NoRatesConfigured = 'no_rates_configured';
}
