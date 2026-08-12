<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Support;

use Closure;
use Illuminate\Database\QueryException;
use Liberu\Ecommerce\Shipping\Exceptions\IdempotencyKeyConflict;
use Liberu\Ecommerce\Shipping\Exceptions\IdempotencyKeyInFlight;
use Liberu\Ecommerce\Shipping\Models\IdempotencyKey;

/**
 * The two-class idempotency scheme.
 *
 * A permanent conflict — the same key with a different payload — and a
 * transient in-flight duplicate are opposite instructions to a caller, so they
 * are two classes and are told apart by `instanceof`. Nothing decodes a message.
 */
final class IdempotencyGuard
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  Closure(): string  $work
     */
    public function execute(string $tenantId, string $operation, string $key, array $payload, Closure $work): string
    {
        $hash = self::hash($payload);
        $existing = $this->find($tenantId, $operation, $key);

        if ($existing !== null) {
            $this->assertReplayable($existing, $hash, $key, $operation);

            return (string) $existing->result_reference;
        }

        try {
            $record = IdempotencyKey::query()->create([
                'tenant_id' => $tenantId,
                'operation' => $operation,
                'idempotency_key' => $key,
                'payload_hash' => $hash,
                'state' => IdempotencyKey::STATE_IN_FLIGHT,
            ]);
        } catch (QueryException) {
            // Lost the race to the unique index: whoever won owns the answer.
            $winner = $this->find($tenantId, $operation, $key);

            if ($winner === null) {
                throw IdempotencyKeyInFlight::key($key);
            }

            $this->assertReplayable($winner, $hash, $key, $operation);

            return (string) $winner->result_reference;
        }

        $reference = $work();

        $record->update([
            'state' => IdempotencyKey::STATE_COMPLETED,
            'result_reference' => $reference,
        ]);

        return $reference;
    }

    /** @param  array<string, mixed>  $payload */
    public static function hash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', (string) json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function find(string $tenantId, string $operation, string $key): ?IdempotencyKey
    {
        return IdempotencyKey::query()
            ->where('tenant_id', $tenantId)
            ->where('operation', $operation)
            ->where('idempotency_key', $key)
            ->first();
    }

    private function assertReplayable(IdempotencyKey $record, string $hash, string $key, string $operation): void
    {
        if ($record->payload_hash !== $hash) {
            throw IdempotencyKeyConflict::key($key, $operation);
        }

        if ($record->state !== IdempotencyKey::STATE_COMPLETED) {
            throw IdempotencyKeyInFlight::key($key);
        }
    }
}
