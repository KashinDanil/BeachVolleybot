<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Common\LocationUpdateThrottle;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Telegram\CallbackData\CallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class SetLiveLocationProcessor extends AbstractActionProcessor
{
    private static ?LocationUpdateThrottle $throttle = null;

    /** @internal For testing only */
    public static function resetThrottle(): void
    {
        self::$throttle = null;
    }

    public function process(TelegramUpdate $update): void
    {
        $editedMessage = $update->editedMessage;

        if (!$editedMessage->hasLocation()) {
            return;
        }

        if (!$editedMessage->hasReplyToMessage()) {
            return;
        }

        $inlineQueryId = CallbackData::extractInlineQueryId($editedMessage->replyToMessage);

        if (null === $inlineQueryId) {
            return;
        }

        if (self::getThrottle()->isThrottled($inlineQueryId)) {
            return;
        }

        $gameManager = new GameManager();
        $gameLookup = $gameManager->resolveGameByInlineQueryId($inlineQueryId);

        if (null === $gameLookup) {
            return;
        }

        if (!$gameManager->isPlayerInGame($gameLookup->gameId, $editedMessage->from->id)) {
            return;
        }

        $location = $gameManager->setLocation($gameLookup->gameId, $editedMessage->location->latitude, $editedMessage->location->longitude);
        $this->logUserAction($editedMessage->from, 'update_live_location', "gameId=$gameLookup->gameId;location=$location");

        self::getThrottle()->touch($inlineQueryId);
        $this->refreshInlineMessage($gameLookup->inlineMessageId);
    }

    private static function getThrottle(): LocationUpdateThrottle
    {
        return self::$throttle ??= new LocationUpdateThrottle();
    }
}
