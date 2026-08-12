<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Data;

use Liberu\Ecommerce\Shipping\Enums\BandAxis;
use Liberu\Ecommerce\Shipping\Enums\RateType;

/** What an operator authors when they price a service level in a zone. */
final readonly class RateDefinition
{
    /** @var list<BandDefinition> */
    public array $bands;

    /** @param  list<BandDefinition>  $bands */
    public function __construct(
        public int $zoneId,
        public int $serviceLevelId,
        public RateType $rateType,
        public string $currency,
        public TransitEstimate $estimate,
        public ?int $amountMinor = null,
        public ?BandAxis $bandAxis = null,
        array $bands = [],
        public ?int $freeAboveSubtotalMinor = null,
        public bool $isActive = true,
    ) {
        $this->bands = array_values($bands);
    }
}
