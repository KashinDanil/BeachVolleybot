<?php

declare(strict_types=1);

namespace BeachVolleybot\Common;

enum Command: string
{
    case Help = '/help';
    case Start = '/start';
    case Settings = '/settings';
    case Games = '/games';
    case NewGame = '/new_game';

    public function matches(?string $text): bool
    {
        return $this->value === $text || $this->mention() === $text;
    }

    public function mention(): string
    {
        return $this->value . '@' . BOT_USERNAME;
    }
}
