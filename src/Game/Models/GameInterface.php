<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\Models;

use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\Telegram\Messages\Targets\GameMessageTarget;
use DateTimeImmutable;

interface GameInterface
{
    public function getGameId(): int;

    public function getGameKey(): string;

    /** @return list<GameMessageTarget> */
    public function getMessageTargets(): array;

    public function getTitle(): string;

    public function getLocation(): ?string;

    public function getTime(): string;

    public function getCreatedAt(): DateTimeImmutable;

    /** @return UserInterface[] */
    public function getUsers(): array;

    public function buildTelegramMessage(): TelegramMessage;

}
