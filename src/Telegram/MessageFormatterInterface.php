<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram;

interface MessageFormatterInterface
{
    public function parseMode(): string;

    public function escape(string $text): string;

    public function bold(string $text): string;

    public function italic(string $text): string;

    public function underline(string $text): string;

    public function code(string $text): string;

    public function codeBlock(string $text): string;

    public function blockquote(string $text): string;

    public function link(string $text, string $url): string;

    public function customEmoji(string $placeholder, string $emojiId): string;
}
