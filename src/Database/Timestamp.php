<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

use DateTimeImmutable;
use DateTimeZone;

/** Every timestamp column holds UTC, whatever zone the server runs in. */
final class Timestamp
{
    private const string FORMAT = 'Y-m-d H:i:s';

    public static function format(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format(self::FORMAT);
    }

    public static function parse(string $timestamp): DateTimeImmutable
    {
        return new DateTimeImmutable($timestamp, new DateTimeZone('UTC'));
    }
}
