<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Data;

use Liberu\Ecommerce\Shipping\Enums\PriceKind;
use Liberu\Ecommerce\Shipping\Enums\PriceStatus;

/**
 * A recorded shipping price, read back whole.
 *
 * `total()` is a fold over the price line and its adjustments. No column
 * anywhere holds the sum, so no column can disagree with it.
 */
final readonly class RecordedPrice
{
    /** @var list<RecordedAdjustment> */
    public array $adjustments;

    /** @var list<Parcel> */
    public array $parcels;

    /**
     * @param  list<RecordedAdjustment>  $adjustments
     * @param  list<Parcel>  $parcels
     */
    public function __construct(
        public string $reference,
        public PriceKind $kind,
        public PriceStatus $status,
        public Money $amount,
        public ?TransitEstimate $estimate,
        public string $serviceLevelCode,
        public string $serviceLevelName,
        public Destination $destination,
        public PriceProvenance $provenance,
        array $adjustments = [],
        array $parcels = [],
        public ?string $selectedAt = null,
        public ?string $expiresAt = null,
    ) {
        $this->adjustments = array_values($adjustments);
        $this->parcels = array_values($parcels);
    }

    public function total(): Money
    {
        return array_reduce(
            $this->adjustments,
            static fn (Money $carry, RecordedAdjustment $adjustment): Money => $carry->plus($adjustment->amount),
            $this->amount,
        );
    }
}
