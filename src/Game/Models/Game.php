<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\Models;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Telegram\MessageBuilders\GameMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use RuntimeException;

final class Game implements GameInterface
{
    private ?string $time = null;

    /** @param PlayerInterface[] $players */
    public function __construct(
        private readonly int $gameId,
        private readonly string $inlineQueryId,
        private readonly string $inlineMessageId,
        public string $title,
        public array $players,
        public ?string $location = null,
        public GameMessageBuilder $telegramMessageBuilder = new GameMessageBuilder(),
    ) {
    }

    public function init(): void
    {
        $this->time = TimeExtractor::extract($this->title);
    }

    public function getGameId(): int
    {
        return $this->gameId;
    }

    public function getInlineQueryId(): string
    {
        return $this->inlineQueryId;
    }

    public function getInlineMessageId(): string
    {
        return $this->inlineMessageId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function getTime(): string
    {
        if (null === $this->time) {
            throw new RuntimeException("Time not found in title: $this->title");
        }

        return $this->time;
    }

    /** @return PlayerInterface[] */
    public function getPlayers(): array
    {
        return $this->players;
    }

    public function buildTelegramMessage(): TelegramMessage
    {
        return $this->telegramMessageBuilder->build($this);
    }
}
