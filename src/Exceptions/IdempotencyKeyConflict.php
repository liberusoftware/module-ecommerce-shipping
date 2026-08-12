<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Exceptions;

/**
 * Permanent: the same key was already used for a different payload. A caller
 * retrying will get this forever, so the transport answers 409.
 */
final class IdempotencyKeyConflict extends ShippingException
{
    private function __construct(string $message, public readonly string $key)
    {
        parent::__construct($message);
    }

    public static function key(string $key, string $operation): self
    {
        return new self("Idempotency key [{$key}] was already used for a different [{$operation}] payload.", $key);
    }
}
