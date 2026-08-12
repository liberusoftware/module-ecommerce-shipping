<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Enums;

/**
 * The kind of thing a recorded shipping price is. Stored, never inferred.
 *
 * A derived price is computed here from rules this module holds, and is
 * reproducible from them. A quoted price is an answer a third party gave at an
 * instant about a future physical movement; nothing recorded here will ever let
 * this module recompute one, which is why it is stored verbatim.
 */
enum PriceKind: string
{
    case Derived = 'derived';
    case Quoted = 'quoted';
}
