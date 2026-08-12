<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Support;

use Liberu\Ecommerce\Shipping\Data\BandDefinition;
use Liberu\Ecommerce\Shipping\Enums\BandAxis;
use Liberu\Ecommerce\Shipping\Exceptions\RateBandsDoNotTileAxis;

/**
 * A table rate is a lookup over declared bands, and the bands must tile the axis.
 *
 * Checked when the rate is written, not when a buyer is quoted: a gap found at
 * quote time is a price nobody can produce, discovered by the one person who
 * cannot fix it.
 */
final class BandTiling
{
    /**
     * @param  list<BandDefinition>  $bands
     * @return list<BandDefinition> the bands in axis order
     */
    public static function assertTiles(BandAxis $axis, array $bands): array
    {
        if ($bands === []) {
            throw RateBandsDoNotTileAxis::empty($axis);
        }

        $ordered = $bands;
        usort($ordered, static fn (BandDefinition $a, BandDefinition $b): int => $a->lowerBound <=> $b->lowerBound);

        if ($ordered[0]->lowerBound !== 0) {
            throw RateBandsDoNotTileAxis::doesNotStartAtZero($axis, $ordered[0]->lowerBound);
        }

        $last = count($ordered) - 1;

        foreach ($ordered as $index => $band) {
            if ($band->isUnbounded && $index !== $last) {
                throw RateBandsDoNotTileAxis::unboundedBandIsNotLast($axis);
            }

            if ($band->isUnbounded) {
                continue;
            }

            $upper = (int) $band->upperBound;

            if ($upper <= $band->lowerBound) {
                throw RateBandsDoNotTileAxis::boundedBandIsEmpty($axis, $band->lowerBound, $upper);
            }

            if ($index === $last) {
                throw RateBandsDoNotTileAxis::missingUnboundedTopBand($axis);
            }

            $next = $ordered[$index + 1];

            if ($next->lowerBound > $upper) {
                throw RateBandsDoNotTileAxis::gap($axis, $upper, $next->lowerBound);
            }

            if ($next->lowerBound < $upper) {
                throw RateBandsDoNotTileAxis::overlap($axis, $upper, $next->lowerBound);
            }
        }

        return array_values($ordered);
    }

    /**
     * @param  list<BandDefinition>  $bands
     */
    public static function pick(array $bands, int $value): ?BandDefinition
    {
        foreach ($bands as $band) {
            if ($band->contains($value)) {
                return $band;
            }
        }

        return null;
    }
}
