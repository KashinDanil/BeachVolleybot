<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;
use DateTimeImmutable;

readonly class NewGameData
{
    public const int INITIAL_VOLLEYBALL = 1;
    public const int INITIAL_NET = 1;
    public const int INITIAL_POSITION = 1;

    private function __construct(
        public TelegramUser $creator,
        public string $title,
        public string $gameKey,
        public DateTimeImmutable $createdAt,
    ) {
    }

    public static function fromUser(
        TelegramUser $creator,
        string $title,
        string $gameKey,
        ?DateTimeImmutable $createdAt = null,
    ): self {
        return new self(
            creator: $creator,
            title: TimeExtractor::normalize($title),
            gameKey: $gameKey,
            createdAt: $createdAt ?? new DateTimeImmutable(),
        );
    }
}
