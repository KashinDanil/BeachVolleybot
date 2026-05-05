<?php

declare(strict_types=1);

namespace BeachVolleybot\Common\Extractors;

final class ForwardGameQueryExtractor
{
    public const string PREFIX = 'Forward game';

    public static function extract(string $query): ?int
    {
        $pattern = '/^' . preg_quote(self::PREFIX, '/') . '\s+(\d+)$/iu';

        if (1 !== preg_match($pattern, $query, $matches)) {
            return null;
        }

        $gameId = (int)$matches[1];

        if (0 >= $gameId) {
            return null;
        }

        return $gameId;
    }
}
