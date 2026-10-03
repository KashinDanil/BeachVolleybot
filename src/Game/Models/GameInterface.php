<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\Models;

use BeachVolleybot\Game\GameMessageRecord;
use BeachVolleybot\Game\GameSettings;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use DateTimeImmutable;

interface GameInterface
{
    public function getGameId(): int;

    public function getGameKey(): string;

    /** @return list<GameMessageRecord> */
    public function getMessages(): array;

    public function getTitle(): string;

    public function getLocation(): ?string;

    public function getTime(): string;

    public function getCreatedAt(): DateTimeImmutable;

    public function getKickoffAt(): DateTimeImmutable;

    public function getVenueName(): ?string;

    public function getSettings(): GameSettings;

    /** @return PlayerInterface[] */
    public function getPlayers(): array;

    public function buildTelegramMessage(?string $inlineQueryId = null): TelegramMessage;

}
