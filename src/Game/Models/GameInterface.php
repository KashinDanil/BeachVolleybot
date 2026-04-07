<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\Models;

use BeachVolleybot\Telegram\MessageBuilders\TelegramMessageBuilderInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

interface GameInterface
{
    public function getTelegramMessageBuilder(): TelegramMessageBuilderInterface;
    public function getGameId(): int;

    public function getInlineQueryId(): string;

    public function getInlineMessageId(): string;

    public function getTitle(): string;

    public function getLocation(): ?string;

    public function getTime(): string;

    /** @return PlayerInterface[] */
    public function getPlayers(): array;

    public function buildTelegramMessage(): TelegramMessage;

}
