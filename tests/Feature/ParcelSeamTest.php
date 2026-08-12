<?php

declare(strict_types=1);

use Liberu\Ecommerce\Shipping\Actions\ResolveParcels;
use Liberu\Ecommerce\Shipping\Contracts\ResolvesParcels;
use Liberu\Ecommerce\Shipping\Data\Parcel;
use Liberu\Ecommerce\Shipping\Data\ParcelSet;
use Liberu\Ecommerce\Shipping\Exceptions\ParcelResolverNotBound;
use Liberu\Ecommerce\Shipping\Exceptions\ParcelsNotResolved;
use Liberu\Ecommerce\Shipping\Exceptions\ParcelWeightMissing;

it('fails loudly when nothing is bound, because shipping is told its parcels', function () {
    (new ResolveParcels())($this->tenant, 'basket-1');
})->throws(ParcelResolverNotBound::class, 'never looks a weight up');

it('resolves a container-built action without a binding rather than exploding', function () {
    // A nullable, defaulted dependency is how "unbound" reaches the action at all.
    expect(fn () => app(ResolveParcels::class)($this->tenant, 'basket-1'))
        ->toThrow(ParcelResolverNotBound::class);
});

it('tells a basket that resolved to nothing apart from nothing being bound', function () {
    $resolver = new class() implements ResolvesParcels
    {
        public function resolve(string $tenantId, string $basketReference): ?ParcelSet
        {
            return null;
        }
    };

    expect(fn () => (new ResolveParcels($resolver))($this->tenant, 'basket-1'))
        ->toThrow(ParcelsNotResolved::class, 'basket-1');
});

it('returns what the host says is being shipped', function () {
    $resolver = new class() implements ResolvesParcels
    {
        public function resolve(string $tenantId, string $basketReference): ?ParcelSet
        {
            return new ParcelSet(new Parcel(1_500, 400, 300, 200));
        }
    };

    expect((new ResolveParcels($resolver))($this->tenant, 'basket-1')->totalWeightGrams())->toBe(1_500);
});

it('refuses a resolver that hands over an unweighed parcel', function () {
    $resolver = new class() implements ResolvesParcels
    {
        public function resolve(string $tenantId, string $basketReference): ?ParcelSet
        {
            return new ParcelSet(Parcel::fromNullableWeight(null, reference: 'sku-9'));
        }
    };

    (new ResolveParcels($resolver))($this->tenant, 'basket-1');
})->throws(ParcelWeightMissing::class);
