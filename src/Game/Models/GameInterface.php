<?php

declare(strict_types=1);

namespace BeachVolleybot\Game\Models;

use BeachVolleybot\Game\MessageBuilders\MessageBuilderInterface;

interface GameInterface
{
    public function getGameId(): int;

    public function getInlineQueryId(): string;

    public function getInlineMessageId(): string;

    public function getTitle(): string;

    public function getTime(): string;

    /** @return PlayerInterface[] */
    public function getPlayers(): array;

    public function getMessageBuilder(): MessageBuilderInterface;

    public function getFooter(): ?string;
}
