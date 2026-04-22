<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram;

use BeachVolleybot\Game\GameFactory;
use BeachVolleybot\Weather\WeatherEnqueuer;

readonly class InlineMessageRefresher
{
    public function __construct(
        private TelegramMessageSender $sender,
        private WeatherEnqueuer $weatherEnqueuer = new WeatherEnqueuer(),
    ) {
    }

    public function refresh(string $inlineMessageId): void
    {
        $game = GameFactory::fromInlineMessageId($inlineMessageId);
        $message = $game->buildTelegramMessage();

        $this->sender->editInlineMessage($inlineMessageId, $message);
        $this->weatherEnqueuer->enqueue($game->getGameId());
    }
}
