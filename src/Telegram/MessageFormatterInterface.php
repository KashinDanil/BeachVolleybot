<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram;

interface MessageFormatterInterface
{
    public function parseMode(): string;

    public function escape(string $text): string;

    public function link(string $text, string $url): string;
}