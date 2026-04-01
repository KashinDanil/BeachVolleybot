<?php

declare(strict_types=1);

namespace BeachVolleybot\Localization;

use DanilKashin\Localization\JsonFileMissingTranslationHandler;
use DanilKashin\Localization\Language;
use DanilKashin\Localization\Translator as VendorTranslator;

class Translator
{
    private static ?self $instance = null;

    private const string TRANSLATIONS_PATH = __DIR__ . '/../../bin/localization';
    private const string MISSING_TRANSLATIONS_FILE = self::TRANSLATIONS_PATH . '/missing.json';
    private const string DEFAULT_LANGUAGE = Language::EN;

    private readonly VendorTranslator $inner;

    public function __construct(string $language, ?string $missingFile = null)
    {
        $this->inner = new VendorTranslator(
            $language,
            self::TRANSLATIONS_PATH,
            self::DEFAULT_LANGUAGE,
            new JsonFileMissingTranslationHandler($missingFile ?? self::MISSING_TRANSLATIONS_FILE),
        );
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self(Language::EN);
    }

    public static function setInstance(self $translator): void
    {
        self::$instance = $translator;
    }

    public static function setLanguage(string $language): void
    {
        self::setInstance(new self($language));
    }

    public function isDefaultLanguage(): bool
    {
        return $this->inner->isDefaultLanguage();
    }

    /**
     * Returns the translated string for the given English text.
     * Falls back to the English text if no translation is found.
     */
    public static function translate(string $text): string
    {
        return self::getInstance()->inner->translate($text);
    }

    /** Resets all static state. Should only be used in tests. */
    public static function reset(): void
    {
        self::$instance = null;
    }
}