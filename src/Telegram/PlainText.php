<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram;

final readonly class PlainText implements MessageFormatterInterface
{
    public function parseMode(): string
    {
        return '';
    }

    public function newLine(): string
    {
        return "\n";
    }

    public function escape(string $text): string
    {
        return $text;
    }

    public function bold(string $text): string
    {
        return $text;
    }

    public function italic(string $text): string
    {
        return $text;
    }

    public function underline(string $text): string
    {
        return $text;
    }

    public function code(string $text): string
    {
        return $text;
    }

    public function codeBlock(string $text): string
    {
        return $text;
    }

    public function blockquote(string $text): string
    {
        return $text;
    }

    public function expandableBlockquote(string $text): string
    {
        return $text;
    }

    public function link(string $text, string $url): string
    {
        return $text;
    }

    public function customEmoji(string $placeholder, string $emojiId): string
    {
        return $placeholder;
    }
}
