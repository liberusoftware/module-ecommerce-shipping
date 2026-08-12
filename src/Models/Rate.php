<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Liberu\Ecommerce\Shipping\Data\TransitEstimate;
use Liberu\Ecommerce\Shipping\Enums\BandAxis;
use Liberu\Ecommerce\Shipping\Enums\RateType;
use Liberu\Ecommerce\Shipping\Enums\TransitBasis;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $zone_id
 * @property int $service_level_id
 * @property RateType $rate_type
 * @property int|null $amount_minor
 * @property string $currency
 * @property BandAxis|null $band_axis
 * @property int|null $free_above_subtotal_minor
 * @property int $transit_min_days
 * @property int $transit_max_days
 * @property TransitBasis $transit_basis
 * @property bool $is_active
 * @property-read Zone $zone
 * @property-read ServiceLevel $serviceLevel
 * @property-read Collection<int, RateBand> $bands
 */
final class Rate extends Model
{
    protected $table = 'shipping_rates';

    protected $guarded = [];

    protected $attributes = [
        'is_active' => true,
    ];

    protected $casts = [
        'rate_type' => RateType::class,
        'band_axis' => BandAxis::class,
        'transit_basis' => TransitBasis::class,
        'amount_minor' => 'integer',
        'free_above_subtotal_minor' => 'integer',
        'transit_min_days' => 'integer',
        'transit_max_days' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Zone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    /** @return BelongsTo<ServiceLevel, $this> */
    public function serviceLevel(): BelongsTo
    {
        return $this->belongsTo(ServiceLevel::class, 'service_level_id');
    }

    /** @return HasMany<RateBand, $this> */
    public function bands(): HasMany
    {
        return $this->hasMany(RateBand::class, 'rate_id');
    }

    public function estimate(): TransitEstimate
    {
        return new TransitEstimate($this->transit_min_days, $this->transit_max_days, $this->transit_basis);
    }
}
