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

    public function newLine(): string
    {
        return "\n";
    }

    public function escape(string $text): string
    {
        return preg_replace('/([_*\[\]()~`>#+\-=|{}.!\\\\])/', '\\\\$1', $text);
    }

    public function bold(string $text): string
    {
        return '*' . $this->escape($text) . '*';
    }

    public function italic(string $text): string
    {
        return '_' . $this->escape($text) . '_';
    }

    public function underline(string $text): string
    {
        return '__' . $this->escape($text) . '__';
    }

    public function code(string $text): string
    {
        return '`' . $this->escapeCode($text) . '`';
    }

    public function codeBlock(string $text): string
    {
        return "```\n" . $this->escapeCode($text) . "\n```";
    }

    public function blockquote(string $text): string
    {
        // Does not escape — `>` is a structural marker that wraps already-valid
        // MarkdownV2 content (bold, links, etc. inside a blockquote render
        // normally). Callers passing plain text should escape first.
        return '>' . implode("\n>", explode("\n", $text));
    }

    public function expandableBlockquote(string $text): string
    {
        // Collapsed-by-default blockquote: `**` prefix on the first line,
        // `||` suffix on the last line. Telegram renders this as a quote
        // with a tap-to-expand control when the content is tall.
        return '**' . $this->blockquote($text) . '||';
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

    private function escapeCode(string $text): string
    {
        return str_replace(['\\', '`'], ['\\\\', '\\`'], $text);
    }

    private function escapeLinkUrl(string $url): string
    {
        return str_replace(['\\', ')'], ['\\\\', '\\)'], $url);
    }
}
