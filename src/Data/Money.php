<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Data;

use JsonSerializable;
use Liberu\Ecommerce\Shipping\Exceptions\InvalidMoney;

/**
 * Money is an integer count of minor units and the currency it counts in.
 *
 * There is no float anywhere near it, and no decimal column stores it. The
 * decimal form is a presentation string produced from the integer, never the
 * other way round at runtime.
 *
 * @implements JsonSerializable
 */
final readonly class Money implements JsonSerializable
{
    public string $currency;

    public function __construct(public int $minor, string $currency, public int $exponent = 2)
    {
        $code = strtoupper(trim($currency));

        if (preg_match('/^[A-Z]{3}$/', $code) !== 1) {
            throw InvalidMoney::currency($currency);
        }

        if ($exponent < 0 || $exponent > 6) {
            throw InvalidMoney::exponent($exponent);
        }

        $this->currency = $code;
    }

    public static function zero(string $currency, int $exponent = 2): self
    {
        return new self(0, $currency, $exponent);
    }

    /**
     * Decimal string to minor units, by string arithmetic.
     *
     * `(int) (19.99 * 100)` is 1998, because 19.99 has no binary representation.
     * Splitting on the point, padding the fraction to the currency's exponent
     * and casting the concatenation once is exact for every input.
     */
    public static function fromDecimalString(string $amount, string $currency, int $exponent = 2): self
    {
        $trimmed = trim($amount);

        if (preg_match('/^-?\d+(\.\d+)?$/', $trimmed) !== 1) {
            throw InvalidMoney::decimalString($amount);
        }

        $negative = str_starts_with($trimmed, '-');
        $digits = ltrim($trimmed, '-');
        $whole = $digits;
        $fraction = '';

        if (str_contains($digits, '.')) {
            [$whole, $fraction] = explode('.', $digits, 2);
        }

        if (strlen($fraction) > $exponent) {
            throw InvalidMoney::tooPrecise($amount, $exponent);
        }

        $minor = (int) ($whole.str_pad($fraction, $exponent, '0'));

        return new self($negative ? -$minor : $minor, $currency, $exponent);
    }

    /** The presentation form. A string, always: a float here would undo the point of the class. */
    public function decimal(): string
    {
        $sign = $this->minor < 0 ? '-' : '';
        $digits = (string) abs($this->minor);

        if ($this->exponent === 0) {
            return $sign.$digits;
        }

        $digits = str_pad($digits, $this->exponent + 1, '0', STR_PAD_LEFT);

        return $sign.substr($digits, 0, -$this->exponent).'.'.substr($digits, -$this->exponent);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency, $this->exponent);
    }

    /** A percentage is integer basis points, so a fuel surcharge never introduces a float. */
    public function applyBasisPoints(int $basisPoints): self
    {
        return new self(intdiv($this->minor * $basisPoints, 10_000), $this->currency, $this->exponent);
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor
            && $this->currency === $other->currency
            && $this->exponent === $other->exponent;
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency || $this->exponent !== $other->exponent) {
            throw InvalidMoney::mismatch($this->currency, $other->currency);
        }
    }

    /** @return array{minor: int, currency: string, exponent: int, decimal: string} */
    public function jsonSerialize(): array
    {
        return [
            'minor' => $this->minor,
            'currency' => $this->currency,
            'exponent' => $this->exponent,
            'decimal' => $this->decimal(),
        ];
    }
}
