<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $code
 * @property string $name
 * @property int $precedence
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ZoneTerritory> $territories
 * @property-read Collection<int, Rate> $rates
 */
final class Zone extends Model
{
    protected $table = 'shipping_zones';

    protected $guarded = [];

    protected $attributes = [
        'precedence' => 0,
        'is_active' => true,
    ];

    protected $casts = [
        'precedence' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return HasMany<ZoneTerritory, $this> */
    public function territories(): HasMany
    {
        return $this->hasMany(ZoneTerritory::class, 'zone_id');
    }

    /** @return HasMany<Rate, $this> */
    public function rates(): HasMany
    {
        return $this->hasMany(Rate::class, 'zone_id');
    }
}
