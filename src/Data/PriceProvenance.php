<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Data;

use Liberu\Ecommerce\Shipping\Enums\PriceKind;

/**
 * Where a recorded price came from.
 *
 * There are exactly two shapes and the stored discriminator must agree with the
 * one a row carries. A price with neither, or with both, is not a case this
 * module can produce.
 */
interface PriceProvenance
{
    public function kind(): PriceKind;

    public function describe(): string;
}
