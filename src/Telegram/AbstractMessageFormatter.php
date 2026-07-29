<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram;

abstract readonly class AbstractMessageFormatter implements MessageFormatterInterface
{
    public function style(string $text, Style ...$styles): string
    {
        $result = $this->escape($text);

        foreach ($styles as $style) {
            $result = $this->wrapStyle($result, $style);
        }

        return $result;
    }

    abstract protected function wrapStyle(string $text, Style $style): string;

    public function bold(string $text): string
    {
        return $this->style($text, Style::Bold);
    }

    public function italic(string $text): string
    {
        return $this->style($text, Style::Italic);
    }

    public function underline(string $text): string
    {
        return $this->style($text, Style::Underline);
    }
}
