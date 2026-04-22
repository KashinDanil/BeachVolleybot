<?php

declare(strict_types=1);

namespace BeachVolleybot\Weather\Location\Models;

final readonly class DefaultLocationCoordinates extends LocationCoordinates
{
    public const float DEFAULT_LATITUDE = 41.3942; //Playa de Bogatell, Barcelona
    public const float DEFAULT_LONGITUDE = 2.2071;

    public function __construct()
    {
        parent::__construct(self::DEFAULT_LATITUDE, self::DEFAULT_LONGITUDE);
    }
}
