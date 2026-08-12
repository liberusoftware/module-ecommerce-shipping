<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Actions;

use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\Shipping\Data\Money;
use Liberu\Ecommerce\Shipping\Data\RecordedPrice;
use Liberu\Ecommerce\Shipping\Events\ShippingPriceAdjusted;
use Liberu\Ecommerce\Shipping\Exceptions\UnknownShippingPrice;
use Liberu\Ecommerce\Shipping\Models\ShippingPrice;
use Liberu\Ecommerce\Shipping\Queries\GetShippingPrice;
use Liberu\Ecommerce\Shipping\Support\IdempotencyGuard;

/**
 * Records a surcharge, handling fee or reduction as its own line.
 *
 * It never touches the price it modifies. The host added a config float to a
 * stored carrier quote and left the order charged an amount that matched no
 * quote anyone had ever fetched; here the price and every adjustment to it are
 * separately recorded and separately readable, and the charge is their sum.
 */
final class RecordPriceAdjustment
{
    public const OPERATION = 'record_price_adjustment';

    public function __construct(
        private GetShippingPrice $price,
        private IdempotencyGuard $guard = new IdempotencyGuard(),
    ) {}

    public function __invoke(
        string $tenantId,
        string $reference,
        string $reasonCode,
        string $reason,
        ?int $amountMinor = null,
        ?int $basisPoints = null,
        ?string $idempotencyKey = null,
    ): RecordedPrice {
        $record = function () use ($tenantId, $reference, $reasonCode, $reason, $amountMinor, $basisPoints): string {
            $price = ShippingPrice::query()
                ->where('tenant_id', $tenantId)
                ->where('reference', $reference)
                ->first();

            if (! $price instanceof ShippingPrice) {
                throw UnknownShippingPrice::reference($reference);
            }

            $amount = $basisPoints !== null
                ? $price->money()->applyBasisPoints($basisPoints)
                : new Money((int) $amountMinor, $price->currency);

            $price->adjustments()->create([
                'tenant_id' => $tenantId,
                'amount_minor' => $amount->minor,
                'currency' => $amount->currency,
                'basis_points' => $basisPoints,
                'reason_code' => $reasonCode,
                'reason' => $reason,
            ]);

            Event::dispatch(new ShippingPriceAdjusted($tenantId, $price->reference, $amount->minor, $amount->currency, $reasonCode));

            return $price->reference;
        };

        $adjusted = $idempotencyKey === null
            ? $record()
            : $this->guard->execute($tenantId, self::OPERATION, $idempotencyKey, [
                'reference' => $reference,
                'reason_code' => $reasonCode,
                'amount_minor' => $amountMinor,
                'basis_points' => $basisPoints,
            ], $record);

        return ($this->price)($tenantId, $adjusted);
    }
}
