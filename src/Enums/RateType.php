<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Enums;

enum RateType: string
{
    case Flat = 'flat';
    case Table = 'table';
}
