<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\Models;

use BeachVolleybot\Telegram\MessageBuilders\DefaultTelegramMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\TelegramMessageBuilderInterface;

readonly class GameWithTime extends Game
{
    /** @param PlayerInterface[] $players */
    public function __construct(
        int $gameId,
        string $inlineQueryId,
        string $inlineMessageId,
        string $title,
        array $players,
        private string $time,
        ?string $location = null,
        TelegramMessageBuilderInterface $telegramMessageBuilder = new DefaultTelegramMessageBuilder(),
    ) {
        parent::__construct(
            gameId: $gameId,
            inlineQueryId: $inlineQueryId,
            inlineMessageId: $inlineMessageId,
            title: $title,
            players: $players,
            location: $location,
            telegramMessageBuilder: $telegramMessageBuilder,
        );
    }

    public function getTime(): string
    {
        return $this->time;
    }
}