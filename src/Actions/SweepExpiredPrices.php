<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\Shipping\Enums\PriceStatus;
use Liberu\Ecommerce\Shipping\Events\ExpiredShippingPricesSwept;
use Liberu\Ecommerce\Shipping\Models\ShippingPrice;

/**
 * Removes offers nobody took.
 *
 * A selected price is excluded by the query itself, not by a model hook: model
 * events do not fire for `query()->delete()`, so a guarantee enforced only in a
 * hook would have a hole exactly here. The host pruned on `expires_at` alone and
 * destroyed the only record of why an order was charged what it was charged.
 */
final class SweepExpiredPrices
{
    public function __invoke(string $tenantId, ?Carbon $before = null): int
    {
        $swept = ShippingPrice::query()
            ->where('tenant_id', $tenantId)
            ->where('status', PriceStatus::Offered->value)
            ->whereNull('selected_at')
            ->where('expires_at', '<', $before ?? Carbon::now())
            ->delete();

        Event::dispatch(new ExpiredShippingPricesSwept($tenantId, $swept));

        return $swept;
    }
}
