<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Enums;

/** The integer axis a table rate's bands are looked up on. */
enum BandAxis: string
{
    case WeightGrams = 'weight_grams';
    case SubtotalMinor = 'subtotal_minor';
    case ItemCount = 'item_count';
}
