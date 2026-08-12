<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Shipping\Data\Destination;
use Liberu\Ecommerce\Shipping\Data\Money;
use Liberu\Ecommerce\Shipping\Data\TransitEstimate;
use Liberu\Ecommerce\Shipping\Enums\AppliedRule;
use Liberu\Ecommerce\Shipping\Enums\PriceKind;
use Liberu\Ecommerce\Shipping\Enums\PriceStatus;
use Liberu\Ecommerce\Shipping\Enums\TransitBasis;
use Liberu\Ecommerce\Shipping\Exceptions\ShippingPriceImmutable;

/**
 * A recorded shipping price: derived from this module's rules, or quoted by a
 * carrier and therefore irreproducible.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $reference
 * @property PriceKind $kind
 * @property PriceStatus $status
 * @property int $amount_minor
 * @property string $currency
 * @property int|null $transit_min_days
 * @property int|null $transit_max_days
 * @property TransitBasis|null $transit_basis
 * @property string $service_level_code
 * @property string $service_level_name
 * @property string $destination_country
 * @property string|null $destination_subdivision
 * @property string|null $destination_postcode
 * @property int|null $subtotal_minor
 * @property int|null $item_count
 * @property int|null $zone_id
 * @property string|null $zone_code
 * @property int|null $rate_id
 * @property int|null $rate_band_id
 * @property AppliedRule|null $applied_rule
 * @property string|null $carrier_code
 * @property string|null $carrier_service_code
 * @property string|null $carrier_rate_reference
 * @property Carbon|null $quoted_at
 * @property Carbon $expires_at
 * @property Carbon|null $selected_at
 * @property-read Collection<int, PriceParcel> $parcels
 * @property-read Collection<int, PriceAdjustment> $adjustments
 */
final class ShippingPrice extends Model
{
    protected $table = 'shipping_prices';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'offered',
    ];

    protected $casts = [
        'kind' => PriceKind::class,
        'status' => PriceStatus::class,
        'applied_rule' => AppliedRule::class,
        'transit_basis' => TransitBasis::class,
        'amount_minor' => 'integer',
        'transit_min_days' => 'integer',
        'transit_max_days' => 'integer',
        'subtotal_minor' => 'integer',
        'item_count' => 'integer',
        'zone_id' => 'integer',
        'rate_id' => 'integer',
        'rate_band_id' => 'integer',
        'quoted_at' => 'datetime',
        'expires_at' => 'datetime',
        'selected_at' => 'datetime',
    ];

    /**
     * A selected price is evidence and may not be edited.
     *
     * This hook does not fire for `query()->update()`, which is exactly why the
     * guarantee is also asserted in the actions and documented as a known limit
     * rather than claimed as an invariant of the table.
     */
    protected static function booted(): void
    {
        self::updating(function (self $price): void {
            if ($price->getRawOriginal('status') === PriceStatus::Selected->value && $price->isDirty(['amount_minor', 'currency', 'kind', 'carrier_rate_reference', 'zone_id', 'rate_id', 'rate_band_id', 'applied_rule'])) {
                throw ShippingPriceImmutable::selected((string) $price->getRawOriginal('reference'));
            }
        });
    }

    /** @return HasMany<PriceParcel, $this> */
    public function parcels(): HasMany
    {
        return $this->hasMany(PriceParcel::class, 'shipping_price_id');
    }

    /** @return HasMany<PriceAdjustment, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(PriceAdjustment::class, 'shipping_price_id');
    }

    public function money(): Money
    {
        return new Money($this->amount_minor, $this->currency);
    }

    public function estimate(): ?TransitEstimate
    {
        if ($this->transit_min_days === null || $this->transit_max_days === null || $this->transit_basis === null) {
            return null;
        }

        return new TransitEstimate($this->transit_min_days, $this->transit_max_days, $this->transit_basis);
    }

    public function destination(): Destination
    {
        return new Destination($this->destination_country, $this->destination_subdivision, $this->destination_postcode);
    }

    public function hasExpired(): bool
    {
        return $this->status === PriceStatus::Offered && $this->expires_at->isPast();
    }
}
