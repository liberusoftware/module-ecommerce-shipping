<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Liberu\Ecommerce\Shipping\Data\Parcel;

/**
 * The parcel a price was quoted against, in grams and millimetres.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $shipping_price_id
 * @property int $weight_grams
 * @property int|null $length_mm
 * @property int|null $width_mm
 * @property int|null $height_mm
 * @property-read ShippingPrice $price
 */
final class PriceParcel extends Model
{
    protected $table = 'shipping_price_parcels';

    protected $guarded = [];

    protected $casts = [
        'weight_grams' => 'integer',
        'length_mm' => 'integer',
        'width_mm' => 'integer',
        'height_mm' => 'integer',
    ];

    /** @return BelongsTo<ShippingPrice, $this> */
    public function price(): BelongsTo
    {
        return $this->belongsTo(ShippingPrice::class, 'shipping_price_id');
    }

    public function toParcel(): Parcel
    {
        return new Parcel($this->weight_grams, $this->length_mm, $this->width_mm, $this->height_mm);
    }
}
