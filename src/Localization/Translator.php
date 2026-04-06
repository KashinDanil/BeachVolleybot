<?php

declare(strict_types=1);

namespace BeachVolleybot\Localization;

use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;
use DanilKashin\Localization\JsonFileMissingTranslationHandler;
use DanilKashin\Localization\Language;
use DanilKashin\Localization\Translator as VendorTranslator;

readonly class Translator
{
    private const string TRANSLATIONS_PATH = __DIR__ . '/../../localization';
    private const string MISSING_TRANSLATIONS_FILE = self::TRANSLATIONS_PATH . '/missing.json';
    private const string DEFAULT_LANGUAGE = Language::EN;

    private VendorTranslator $inner;

    public function __construct(string $language = self::DEFAULT_LANGUAGE, ?string $missingFile = null)
    {
        $this->inner = new VendorTranslator(
            $language,
            self::TRANSLATIONS_PATH,
            self::DEFAULT_LANGUAGE,
            new JsonFileMissingTranslationHandler($missingFile ?? self::MISSING_TRANSLATIONS_FILE),
        );
    }

    public static function fromUser(TelegramUser $user): self
    {
        return new self(Language::fromCode($user->languageCode ?? self::DEFAULT_LANGUAGE));
    }

    public function isDefaultLanguage(): bool
    {
        return $this->inner->isDefaultLanguage();
    }

    /**
     * Returns the translated string for the given English text.
     * Falls back to the English text if no translation is found.
     */
    public function translate(string $text): string
    {
        return $this->inner->translate($text);
    }
}
