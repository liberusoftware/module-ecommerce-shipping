<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Liberu\Ecommerce\Shipping\Data\BandDefinition;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $rate_id
 * @property int $lower_bound
 * @property int|null $upper_bound
 * @property bool $is_unbounded
 * @property int $amount_minor
 * @property-read Rate $rate
 */
final class RateBand extends Model
{
    protected $table = 'shipping_rate_bands';

    protected $guarded = [];

    protected $attributes = [
        'is_unbounded' => false,
    ];

    protected $casts = [
        'lower_bound' => 'integer',
        'upper_bound' => 'integer',
        'is_unbounded' => 'boolean',
        'amount_minor' => 'integer',
    ];

    /** @return BelongsTo<Rate, $this> */
    public function rate(): BelongsTo
    {
        return $this->belongsTo(Rate::class, 'rate_id');
    }

    public function toDefinition(): BandDefinition
    {
        return new BandDefinition($this->lower_bound, $this->upper_bound, $this->amount_minor, $this->is_unbounded);
    }
}
