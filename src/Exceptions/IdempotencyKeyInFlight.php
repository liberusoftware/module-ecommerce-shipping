<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Exceptions;

/**
 * Transient: the same key, the same payload, still running. A caller should wait
 * and retry, so the transport answers 423 with Retry-After — the opposite
 * instruction to a conflict, which is why it is a different class.
 */
final class IdempotencyKeyInFlight extends ShippingException
{
    private function __construct(string $message, public readonly string $key, public readonly int $retryAfterSeconds)
    {
        parent::__construct($message);
    }

    public static function key(string $key, int $retryAfterSeconds = 2): self
    {
        return new self("Idempotency key [{$key}] is still in flight.", $key, $retryAfterSeconds);
    }
}
