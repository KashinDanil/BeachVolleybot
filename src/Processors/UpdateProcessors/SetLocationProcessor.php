<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Telegram\CallbackData\CallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class SetLocationProcessor extends AbstractActionReplyProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;

        if (!$message->hasLocation()) {
            return;
        }

        if (!$message->hasReplyToMessage()) {
            return;
        }

        $inlineQueryId = CallbackData::extractInlineQueryId($message->replyToMessage);

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
        $this->logUserAction($message->from, 'set_location', "gameId=$gameLookup->gameId;location=$location");
        $gameManager->setLocation($gameLookup->gameId, $location);

        $this->reactWithCheckmark($message);
        $this->refreshInlineMessage($gameLookup->inlineMessageId);
    }
}
