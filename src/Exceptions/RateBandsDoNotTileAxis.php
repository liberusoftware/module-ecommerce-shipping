<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Exceptions;

use Liberu\Ecommerce\Shipping\Enums\BandAxis;

/** A band set with a gap, an overlap, or no explicitly unbounded top band is refused. */
final class RateBandsDoNotTileAxis extends ShippingException
{
    private function __construct(string $message, public readonly BandAxis $axis)
    {
        parent::__construct($message);
    }

    public static function empty(BandAxis $axis): self
    {
        return new self("A table rate on [{$axis->value}] declares no bands.", $axis);
    }

    public static function doesNotStartAtZero(BandAxis $axis, int $lowest): self
    {
        return new self("Bands on [{$axis->value}] start at {$lowest}; they must tile the axis from 0.", $axis);
    }

    public static function gap(BandAxis $axis, int $from, int $to): self
    {
        return new self("Bands on [{$axis->value}] leave a gap between {$from} and {$to}.", $axis);
    }

    public static function overlap(BandAxis $axis, int $from, int $to): self
    {
        return new self("Bands on [{$axis->value}] overlap between {$to} and {$from}.", $axis);
    }

    public static function missingUnboundedTopBand(BandAxis $axis): self
    {
        return new self("Bands on [{$axis->value}] declare no unbounded top band. Declare one explicitly; a null upper bound does not imply it.", $axis);
    }

    public static function unboundedBandIsNotLast(BandAxis $axis): self
    {
        return new self("Bands on [{$axis->value}] declare more than one unbounded band, or place it below a bounded one.", $axis);
    }

    public static function boundedBandIsEmpty(BandAxis $axis, int $lower, int $upper): self
    {
        return new self("A band on [{$axis->value}] spans [{$lower}, {$upper}), which contains nothing.", $axis);
    }
}
