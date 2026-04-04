<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Game\MessageBuilders\DefaultMessageBuilder;
use BeachVolleybot\Game\MessageBuilders\MessageBuilderInterface;
use RuntimeException;

readonly class Game implements GameInterface
{
    /** @param PlayerInterface[] $players */
    public function __construct(
        private int $gameId,
        private string $inlineMessageId,
        private string $header,
        private array $players,
        private MessageBuilderInterface $messageBuilder = new DefaultMessageBuilder(),
    ) {
    }

    public function getGameId(): int
    {
        return $this->gameId;
    }

    public function getInlineMessageId(): string
    {
        return $this->inlineMessageId;
    }

    public function getHeader(): string
    {
        return $this->header;
    }

    public function getTime(): string
    {
        $time = TimeExtractor::extract($this->header);

        if (null === $time) {
            throw new RuntimeException("Time not found in header: $this->header");
        }

        return $time;
    }

    /** @return PlayerInterface[] */
    public function getPlayers(): array
    {
        return $this->players;
    }

    public function getMessageBuilder(): MessageBuilderInterface
    {
        return $this->messageBuilder;
    }

    public function getFooter(): ?string
    {
        return null;
    }
}
