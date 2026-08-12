<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Enums;

enum RestrictionType: string
{
    case MaxWeightGrams = 'max_weight_grams';
    case MaxDimensionMm = 'max_dimension_mm';
    case MinSubtotalMinor = 'min_subtotal_minor';
    case DestinationExcluded = 'destination_excluded';
}
