<?php

declare(strict_types=1);

namespace BeachVolleybot\Common\Extractors;

use BeachVolleybot\Localization\Translator;

final class ForwardGameQueryExtractor
{
    public const string TRANSLATION_KEY = 'Forward game';

    public static function extract(string $query, Translator $translator): ?int
    {
        $localizedPrefix = $translator->translate(self::TRANSLATION_KEY);
        $pattern = '/^' . preg_quote($localizedPrefix, '/') . '\s+(\d+)$/iu';

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
