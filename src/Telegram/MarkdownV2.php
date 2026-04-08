<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram;

final readonly class MarkdownV2 implements MessageFormatterInterface
{
    private const string PARSE_MODE = 'MarkdownV2';

    public function parseMode(): string
    {
        return self::PARSE_MODE;
    }

    public function escape(string $text): string
    {
        return preg_replace('/([_*\[\]()~`>#+\-=|{}.!\\\\])/', '\\\\$1', $text);
    }

    public function bold(string $text): string
    {
        return '*' . $text . '*';
    }

    public function italic(string $text): string
    {
        return '_' . $text . '_';
    }

    public function underline(string $text): string
    {
        return '__' . $text . '__';
    }

    public function code(string $text): string
    {
        return '`' . str_replace(['\\', '`'], ['\\\\', '\\`'], $text) . '`';
    }

    public function codeBlock(string $text): string
    {
        return "```\n" . str_replace(['\\', '`'], ['\\\\', '\\`'], $text) . "\n```";
    }

    public function blockquote(string $text): string
    {
        return '>' . implode("\n>", explode("\n", $this->escape($text)));
    }

    public function link(string $text, string $url): string
    {
        return '[' . $this->escape($text) . '](' . $this->escapeLinkUrl($url) . ')';
    }

    /**
     * Requires the bot to have a paid username purchased from Fragment,
     * upgraded to bot use for 1000 TON.
     */
    public function customEmoji(string $placeholder, string $emojiId): string
    {
        return '![' . $this->escape($placeholder) . '](tg://emoji?id=' . $emojiId . ')';
    }

    private function escapeLinkUrl(string $url): string
    {
        return str_replace(['\\', ')'], ['\\\\', '\\)'], $url);
    }
}
