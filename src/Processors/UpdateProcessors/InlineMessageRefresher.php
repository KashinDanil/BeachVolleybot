<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Game\GameFactory;
use TelegramBot\Api\BotApi;

readonly class InlineMessageRefresher
{
    public function __construct(
        private BotApi $bot,
    ) {
    }

    public function refresh(string $inlineMessageId): void
    {
        $game = GameFactory::fromInlineMessageId($inlineMessageId);
        $message = $game->buildTelegramMessage();

        $this->bot->editMessageText(
            null,
            null,
            $message->getText()->getMessageText(),
            $message->getText()->getParseMode(),
            $message->getText()->isDisableWebPagePreview(),
            $message->getKeyboard(),
            $inlineMessageId,
        );
    }
}
