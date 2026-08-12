<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Liberu\Ecommerce\Shipping\Data\TerritoryDefinition;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $zone_id
 * @property string $country_code
 * @property string|null $subdivision_code
 * @property string|null $postcode_prefix
 * @property-read Zone $zone
 */
final class ZoneTerritory extends Model
{
    protected $table = 'shipping_zone_territories';

    protected $guarded = [];

    /** @return BelongsTo<Zone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function toDefinition(): TerritoryDefinition
    {
        return new TerritoryDefinition($this->country_code, $this->subdivision_code, $this->postcode_prefix);
    }
}
