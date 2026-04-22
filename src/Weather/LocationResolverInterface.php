<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather;

interface LocationResolverInterface
{
    public function resolve(string $query): ?LocationCoordinates;
}
