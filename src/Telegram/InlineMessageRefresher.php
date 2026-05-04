<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram;

use BeachVolleybot\Game\GameFactory;

readonly class InlineMessageRefresher
{
    public function __construct(
        private TelegramMessageSender $sender,
    ) {
    }

    public function refresh(int $gameId): void
    {
        $game = GameFactory::fromGameId($gameId);
        $message = $game->buildTelegramMessage();

        foreach ($game->getInlineMessageIds() as $inlineMessageId) {
            $this->sender->editInlineMessage($inlineMessageId, $message);
        }
    }
}
