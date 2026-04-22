<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Location\Resolvers;

use BeachVolleybot\Weather\Location\Models\LocationCoordinates;

interface LocationResolverInterface
{
    public function resolve(string $query): ?LocationCoordinates;
}
