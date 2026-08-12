<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\Shipping\Enums\RestrictionType;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int|null $zone_id
 * @property int|null $service_level_id
 * @property RestrictionType $restriction_type
 * @property int|null $threshold
 * @property string $reason_code
 * @property string $reason
 * @property bool $is_active
 */
final class Restriction extends Model
{
    protected $table = 'shipping_restrictions';

    protected $guarded = [];

    protected $attributes = [
        'is_active' => true,
    ];

    protected $casts = [
        'restriction_type' => RestrictionType::class,
        'threshold' => 'integer',
        'is_active' => 'boolean',
    ];
}
