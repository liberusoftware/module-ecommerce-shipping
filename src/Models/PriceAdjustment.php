<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Liberu\Ecommerce\Shipping\Data\Money;

/**
 * A surcharge, handling fee or reduction recorded beside a price — never folded
 * into it.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $shipping_price_id
 * @property int $amount_minor
 * @property string $currency
 * @property int|null $basis_points
 * @property string $reason_code
 * @property string $reason
 * @property-read ShippingPrice $price
 */
final class PriceAdjustment extends Model
{
    protected $table = 'shipping_price_adjustments';

    protected $guarded = [];

    protected $casts = [
        'amount_minor' => 'integer',
        'basis_points' => 'integer',
    ];

    /** @return BelongsTo<ShippingPrice, $this> */
    public function price(): BelongsTo
    {
        return $this->belongsTo(ShippingPrice::class, 'shipping_price_id');
    }

    public function money(): Money
    {
        return new Money($this->amount_minor, $this->currency);
    }
}
