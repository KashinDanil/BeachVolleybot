<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game\AddOns\Stub;

use BeachVolleybot\Game\AddOns\GameAddOnInterface;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;

final class TitlePrefixAddOn implements GameAddOnInterface
{
    public function __construct(private readonly string $prefix = '[Modified]')
    {
    }

    public function transform(GameInterface $game): GameInterface
    {
        return new Game(
            gameId: $game->getGameId(),
            inlineQueryId: $game->getInlineQueryId(),
            inlineMessageId: $game->getInlineMessageId(),
            title: $this->prefix . ' ' . $game->getTitle(),
            players: $game->getPlayers(),
            telegramMessageBuilder: $game->getTelegramMessageBuilder(),
        );
    }
}