<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\Models;

use BeachVolleybot\Common\TimeExtractor;
use BeachVolleybot\Telegram\MessageBuilders\DefaultTelegramMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\TelegramMessageBuilderInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use RuntimeException;

readonly class Game implements GameInterface
{
    /** @param PlayerInterface[] $players */
    public function __construct(
        private int $gameId,
        private string $inlineQueryId,
        private string $inlineMessageId,
        private string $title,
        private array $players,
        private ?string $location = null,
        private TelegramMessageBuilderInterface $telegramMessageBuilder = new DefaultTelegramMessageBuilder(),
    ) {
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
        $time = TimeExtractor::extract($this->title);

        if (null === $time) {
            throw new RuntimeException("Time not found in title: $this->title");
        }

        return $time;
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

    public function getFooter(): ?string
    {
        return null;
    }
}
