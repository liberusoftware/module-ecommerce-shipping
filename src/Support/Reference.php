<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Support;

use Random\RandomException;

/**
 * Opaque public identifiers.
 *
 * `random_bytes` rather than `Str::ulid()`: ULIDs need symfony/uid, which
 * illuminate/support does not require, so reaching for one would pass every CI
 * job here and be a lying constraint for a consumer.
 */
final class Reference
{
    /** @throws RandomException */
    public static function mint(string $prefix = 'shp'): string
    {
        return $prefix.'_'.bin2hex(random_bytes(16));
    }
}
