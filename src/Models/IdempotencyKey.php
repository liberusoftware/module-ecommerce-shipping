<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $operation
 * @property string $idempotency_key
 * @property string $payload_hash
 * @property string $state
 * @property string|null $result_reference
 */
final class IdempotencyKey extends Model
{
    public const STATE_IN_FLIGHT = 'in_flight';

    public const STATE_COMPLETED = 'completed';

    protected $table = 'shipping_idempotency_keys';

    protected $guarded = [];
}
