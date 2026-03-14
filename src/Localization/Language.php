<?php

declare(strict_types=1);

namespace BeachVolleybot\Localization;

enum Language: string
{
    case EN = 'en';
    case RU = 'ru';
    case ES = 'es';

    /** Resolve a Telegram language_code (e.g. "ru", "en-US") to a supported Language, falling back to English. */
    public static function fromCode(string $code): self
    {
        return self::tryFrom(strtolower(substr($code, 0, 2))) ?? self::EN;
    }
}