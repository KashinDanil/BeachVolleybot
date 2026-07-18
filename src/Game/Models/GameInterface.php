<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\Models;

use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use DateTimeImmutable;

interface GameInterface
{
    public function getGameId(): int;

    public function getInlineQueryId(): string;

    /** @return list<string> */
    public function getInlineMessageIds(): array;

    public function getTitle(): string;

    public function getLocation(): ?string;

    public function getTime(): string;

    public function getCreatedAt(): DateTimeImmutable;

    /** @return UserInterface[] */
    public function getUsers(): array;

    public function buildTelegramMessage(): TelegramMessage;

}
