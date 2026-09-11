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
    private const string MISSING_TRANSLATIONS_BASENAME = 'missing.json';
    private const string MISSING_TRANSLATIONS_FILE = self::TRANSLATIONS_PATH . '/' . self::MISSING_TRANSLATIONS_BASENAME;
    private const string DEFAULT_LANGUAGE = Language::EN;

    private VendorTranslator $inner;

    public function __construct(
        private string $language = self::DEFAULT_LANGUAGE,
        ?string $missingFile = null,
    ) {
        $this->inner = new VendorTranslator(
            $this->language,
            self::TRANSLATIONS_PATH,
            self::DEFAULT_LANGUAGE,
            new JsonFileMissingTranslationHandler($missingFile ?? self::MISSING_TRANSLATIONS_FILE),
        );
    }

    public static function fromUser(TelegramUser $user): self
    {
        return new self(Language::fromCode($user->languageCode ?? self::DEFAULT_LANGUAGE));
    }

    public static function supportedLanguages(): array
    {
        $files = glob(self::TRANSLATIONS_PATH . '/*.json') ?: [];

        $translated = array_filter(
            $files,
            static fn(string $path): bool => self::MISSING_TRANSLATIONS_BASENAME !== basename($path),
        );

        $languages = array_map(static fn(string $path): string => basename($path, '.json'), array_values($translated));

        return array_values(array_unique([self::DEFAULT_LANGUAGE, ...$languages]));
    }

    public function language(): string
    {
        return $this->language;
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
        $text = trim($text);
        if ('' === $text) {
            return '';
        }

        return $this->inner->translate($text);
    }
}
