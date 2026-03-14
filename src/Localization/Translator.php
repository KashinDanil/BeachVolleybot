<?php

declare(strict_types=1);

namespace BeachVolleybot\Localization;

final class Translator
{
    private static ?self $instance = null;

    /** @var array<string, array<string, string>> Translations cache shared across all instances, keyed by language value. */
    private static array $translationCache = [];

    /** @var array<string, string[]>|null Missing translation keyed by language value. Null means not yet loaded from the disk. */
    private static ?array $missingTranslation = null;

    private const string MISSING_TRANSLATIONS_FILE = __DIR__ . '/translations/missing.json';

    private const Language DEFAULT_LANGUAGE = Language::EN;

    public function __construct(private readonly Language $language)
    {
        if ($this->isDefaultLanguage()) {
            return;
        }

        if (!isset(self::$translationCache[$language->value])) {
            $file = __DIR__ . '/translations/' . $language->value . '.php';
            self::$translationCache[$language->value] = file_exists($file) ? require $file : [];
        }
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self(Language::EN);
    }

    public static function setInstance(self $translator): void
    {
        self::$instance = $translator;
    }

    public static function setLanguage(Language $language): void
    {
        self::setInstance(new self($language));
    }

    public function isDefaultLanguage(): bool
    {
        return self::DEFAULT_LANGUAGE === $this->language;
    }

    /** Resets all static state. Should only be used in tests. */
    public static function reset(): void
    {
        self::$instance = null;
        self::$translationCache = [];
        self::$missingTranslation = null;
    }

    /**
     * Returns the translated string for the given English text.
     * Falls back to the English text if no translation is found, and records the key in missing.json.
     */
    public static function translate(string $text): string
    {
        $translator = self::getInstance();
        if ($translator->isDefaultLanguage()) {
            return $text;
        }

        if (isset(self::$translationCache[$translator->language->value][$text])) {
            return self::$translationCache[$translator->language->value][$text];
        }

        $translator->trackMissing($text);

        return $text;
    }

    public function trackMissing(string $text): void
    {
        $this->ensureMissingTranslationLoaded();

        $lang = $this->language->value;

        if (!in_array($text, self::$missingTranslation[$lang] ?? [], true)) {
            self::$missingTranslation[$lang][] = $text;

            // Silent fallback: translation errors must never crash the app.
            $encoded = json_encode(self::$missingTranslation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if (false !== $encoded) {
                @file_put_contents(self::MISSING_TRANSLATIONS_FILE, $encoded);
            }
        }
    }

    private function ensureMissingTranslationLoaded(): void
    {
        if (null !== self::$missingTranslation) {
            return;
        }

        self::$missingTranslation = [];

        if (file_exists(self::MISSING_TRANSLATIONS_FILE)) {
            // Silent fallback: corrupt file is treated as empty rather than crashing the app.
            self::$missingTranslation = json_decode(@file_get_contents(self::MISSING_TRANSLATIONS_FILE), true) ?? [];
        }
    }
}