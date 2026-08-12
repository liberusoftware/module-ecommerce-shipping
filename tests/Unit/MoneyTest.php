<?php

declare(strict_types=1);

use Liberu\Ecommerce\Shipping\Data\Money;
use Liberu\Ecommerce\Shipping\Exceptions\InvalidMoney;

it('converts a decimal string to minor units by string arithmetic', function () {
    // The float route gives 1998 for this input, because 19.99 has no binary
    // representation. The reason lives in a test so it survives.
    expect((int) (19.99 * 100))->toBe(1998)
        ->and(Money::fromDecimalString('19.99', 'GBP')->minor)->toBe(1999);
});

it('converts decimal strings of every shape', function (string $given, int $minor) {
    expect(Money::fromDecimalString($given, 'GBP')->minor)->toBe($minor);
})->with([
    ['0', 0],
    ['0.01', 1],
    ['5', 500],
    ['5.5', 550],
    ['-3.25', -325],
    ['1234567.89', 123456789],
]);

it('refuses an amount more precise than the currency', function () {
    Money::fromDecimalString('19.999', 'GBP');
})->throws(InvalidMoney::class);

it('refuses something that is not a decimal amount', function (string $given) {
    Money::fromDecimalString($given, 'GBP');
})->with(['', 'free', '1,99', '1.2.3'])->throws(InvalidMoney::class);

it('renders minor units back as a decimal string', function (int $minor, int $exponent, string $decimal) {
    expect((new Money($minor, 'GBP', $exponent))->decimal())->toBe($decimal);
})->with([
    [1999, 2, '19.99'],
    [5, 2, '0.05'],
    [0, 2, '0.00'],
    [-325, 2, '-3.25'],
    [1500, 0, '1500'],
    [1234, 3, '1.234'],
]);

it('serialises to the settled money envelope with a string decimal', function () {
    $envelope = (new Money(1999, 'gbp'))->jsonSerialize();

    expect($envelope)->toBe(['minor' => 1999, 'currency' => 'GBP', 'exponent' => 2, 'decimal' => '19.99'])
        ->and($envelope['decimal'])->toBeString();
});

it('applies a percentage as integer basis points', function () {
    // 3.5% of £4.99 is 17.465p. Basis points truncate to a whole minor unit; a
    // float multiply would carry a fraction of a penny into a total.
    expect((new Money(499, 'GBP'))->applyBasisPoints(350)->minor)->toBe(17);
});

it('refuses to add money in another currency', function () {
    (new Money(100, 'GBP'))->plus(new Money(100, 'USD'));
})->throws(InvalidMoney::class);

it('adds, compares and reports zero', function () {
    $sum = (new Money(1999, 'GBP'))->plus(new Money(1, 'GBP'));

    expect($sum->minor)->toBe(2000)
        ->and($sum->equals(new Money(2000, 'GBP')))->toBeTrue()
        ->and(Money::zero('GBP')->isZero())->toBeTrue()
        ->and($sum->isZero())->toBeFalse();
});

it('refuses a currency that is not an ISO code', function () {
    new Money(1, 'POUNDS');
})->throws(InvalidMoney::class);

it('refuses an unusable exponent', function () {
    new Money(1, 'GBP', -1);
})->throws(InvalidMoney::class);
