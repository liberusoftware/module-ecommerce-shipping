<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\Shipping\Data\RecordedPrice;
use Liberu\Ecommerce\Shipping\Enums\PriceStatus;
use Liberu\Ecommerce\Shipping\Events\ShippingPriceSelected;
use Liberu\Ecommerce\Shipping\Exceptions\ShippingPriceExpired;
use Liberu\Ecommerce\Shipping\Exceptions\UnknownShippingPrice;
use Liberu\Ecommerce\Shipping\Models\ShippingPrice;
use Liberu\Ecommerce\Shipping\Queries\GetShippingPrice;
use Liberu\Ecommerce\Shipping\Support\IdempotencyGuard;

/**
 * Marks an offered price as the one that priced something.
 *
 * From here the row is evidence: never edited, never swept, and never
 * recomputed. An expired offer is refused rather than silently re-quoted — a
 * caller that gets a different number than the buyer agreed to is the fault this
 * refusal exists to prevent.
 */
final class SelectShippingPrice
{
    public const OPERATION = 'select_shipping_price';

    public function __construct(
        private GetShippingPrice $price,
        private IdempotencyGuard $guard = new IdempotencyGuard(),
    ) {}

    public function __invoke(string $tenantId, string $reference, ?string $idempotencyKey = null): RecordedPrice
    {
        $select = fn (): string => $this->select($tenantId, $reference);

        $selected = $idempotencyKey === null
            ? $select()
            : $this->guard->execute($tenantId, self::OPERATION, $idempotencyKey, ['reference' => $reference], $select);

        return ($this->price)($tenantId, $selected);
    }

    private function select(string $tenantId, string $reference): string
    {
        return DB::transaction(function () use ($tenantId, $reference): string {
            $price = ShippingPrice::query()
                ->where('tenant_id', $tenantId)
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if (! $price instanceof ShippingPrice) {
                throw UnknownShippingPrice::reference($reference);
            }

            if ($price->status === PriceStatus::Selected) {
                return $price->reference;
            }

            if ($price->expires_at->isPast()) {
                throw ShippingPriceExpired::at($reference, $price->expires_at->toIso8601String());
            }

            $price->forceFill([
                'status' => PriceStatus::Selected,
                'selected_at' => now(),
            ])->save();

            Event::dispatch(new ShippingPriceSelected(
                $tenantId,
                $price->reference,
                $price->kind,
                $price->amount_minor,
                $price->currency,
            ));

            return $price->reference;
        });
    }
}
