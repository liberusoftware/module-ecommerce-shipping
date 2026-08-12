<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Enums;

/**
 * What an estimate's day count counts.
 *
 * The module never turns one into a delivery date: that needs a ship date, a
 * cut-off time and a holiday calendar, none of which it owns.
 */
enum TransitBasis: string
{
    case BusinessDays = 'business_days';
    case CalendarDays = 'calendar_days';
}
