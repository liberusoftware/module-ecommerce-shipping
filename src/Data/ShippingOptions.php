<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Data;

use Liberu\Ecommerce\Shipping\Carrier\CarrierRatingOutcome;
use Liberu\Ecommerce\Shipping\Enums\QuoteOutcome;

/** Everything a surface needs to render one destination's shipping choices. */
final readonly class ShippingOptions
{
    /** @var list<ShippingOption> */
    public array $available;

    /** @var list<ExcludedOption> */
    public array $excluded;

    /**
     * @param  list<ShippingOption>  $available
     * @param  list<ExcludedOption>  $excluded
     */
    public function __construct(
        array $available,
        array $excluded,
        public CarrierRatingOutcome $carrierOutcome,
        public QuoteOutcome $outcome,
        public ?string $zoneCode = null,
    ) {
        $this->available = array_values($available);
        $this->excluded = array_values($excluded);
    }

    public function cheapest(): ?ShippingOption
    {
        $cheapest = null;

        foreach ($this->available as $option) {
            if ($cheapest === null || $option->amount->minor < $cheapest->amount->minor) {
                $cheapest = $option;
            }
        }

        return $cheapest;
    }

    public function reference(string $reference): ?ShippingOption
    {
        foreach ($this->available as $option) {
            if ($option->reference === $reference) {
                return $option;
            }
        }

        return null;
    }
}
