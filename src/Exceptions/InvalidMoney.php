<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Exceptions;

final class InvalidMoney extends ShippingException
{
    public static function currency(string $given): self
    {
        return new self("[{$given}] is not a three-letter ISO 4217 currency code.");
    }

    public static function exponent(int $given): self
    {
        return new self("A currency exponent of {$given} is not usable.");
    }

    public static function decimalString(string $given): self
    {
        return new self("[{$given}] is not a decimal amount.");
    }

    public static function tooPrecise(string $given, int $exponent): self
    {
        return new self("[{$given}] carries more precision than a currency with exponent {$exponent} can hold.");
    }

    public static function mismatch(string $left, string $right): self
    {
        return new self("Cannot combine {$left} with {$right}: money in two currencies is two numbers, not one.");
    }
}
