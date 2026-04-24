<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUser;

readonly class NewGameData
{
    public const int INITIAL_VOLLEYBALL = 1;
    public const int INITIAL_NET = 1;
    public const int INITIAL_POSITION = 1;

    private function __construct(
        public int $telegramUserId,
        public string $firstName,
        public ?string $lastName,
        public ?string $username,
        public string $title,
        public string $inlineQueryId,
        public string $inlineMessageId,
    ) {
    }

    public static function fromUser(
        TelegramUser $creator,
        string $title,
        string $inlineQueryId,
        string $inlineMessageId = '',
    ): self {
        return new self(
            telegramUserId: $creator->id,
            firstName: $creator->firstName,
            lastName: $creator->lastName,
            username: $creator->username,
            title: TimeExtractor::normalize($title),
            inlineQueryId: $inlineQueryId,
            inlineMessageId: $inlineMessageId,
        );
    }
}
