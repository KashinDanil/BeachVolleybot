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

    public function link(string $text, string $url): string
    {
        return '[' . $this->escape($text) . '](' . $this->escapeLinkUrl($url) . ')';
    }

    private function escapeLinkUrl(string $url): string
    {
        return str_replace(['\\', ')'], ['\\\\', '\\)'], $url);
    }
}