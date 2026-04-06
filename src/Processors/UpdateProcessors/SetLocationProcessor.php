<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Telegram\MessageBuilders\DefaultTelegramMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class SetLocationProcessor extends AbstractActionReplyProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;

        if (null === $message->location) {
            return;
        }

        if (null === $message->replyToMessage) {
            return;
        }

        $inlineQueryId = DefaultTelegramMessageBuilder::extractInlineQueryId($message->replyToMessage);

        if (null === $inlineQueryId) {
            return;
        }

        $gameManager = new GameManager();
        $gameLookup = $gameManager->resolveGameByInlineQueryId($inlineQueryId);

        if (null === $gameLookup) {
            return;
        }

        if (!$gameManager->isPlayerInGame($gameLookup->gameId, $message->from->id)) {
            return;
        }

        $location = sprintf('%s,%s', $message->location->latitude, $message->location->longitude);
        $gameManager->setLocation($gameLookup->gameId, $location);

        $this->reactWithCheckmark($message);
        $this->refreshInlineMessage($gameLookup->inlineMessageId);
    }
}
