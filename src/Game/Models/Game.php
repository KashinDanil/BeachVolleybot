<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\Models;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Telegram\MessageBuilders\GameMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use DateTimeImmutable;
use RuntimeException;

final class Game implements GameInterface
{
    private ?string $time = null;

    /**
     * @param UserInterface[] $users
     * @param list<string> $inlineMessageIds
     */
    public function __construct(
        private readonly int $gameId,
        private readonly string $inlineQueryId,
        private readonly array $inlineMessageIds,
        public string $title,
        public array $users,
        private readonly DateTimeImmutable $createdAt,
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

    /** @return list<string> */
    public function getInlineMessageIds(): array
    {
        return $this->inlineMessageIds;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getTime(): string
    {
        if (null === $this->time) {
            throw new RuntimeException("Time not found in title: $this->title");
        }

        return $this->time;
    }

    /** @return UserInterface[] */
    public function getUsers(): array
    {
        return $this->users;
    }

    public function buildTelegramMessage(): TelegramMessage
    {
        return $this->telegramMessageBuilder->build($this);
    }
}
