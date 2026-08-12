<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Data;

/** What an operator authors when they define a zone. */
final readonly class ZoneDefinition
{
    /** @var list<TerritoryDefinition> */
    public array $territories;

    /** @param  list<TerritoryDefinition>  $territories */
    public function __construct(
        public string $code,
        public string $name,
        public int $precedence,
        array $territories,
        public bool $isActive = true,
    ) {
        $this->territories = array_values($territories);
    }
}
