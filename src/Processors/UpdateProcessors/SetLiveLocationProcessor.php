<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Common\LocationUpdateThrottle;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramMessage;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class SetLiveLocationProcessor extends AbstractGameReplyProcessor
{
    private static ?LocationUpdateThrottle $throttle = null;

    /** @internal For testing only */
    public static function resetThrottle(): void
    {
        self::$throttle = null;
    }

    protected function extractMessage(TelegramUpdate $update): ?TelegramMessage
    {
        return $update->editedMessage;
    }

    protected function handle(TelegramUpdate $update, GameRecord $gameRecord): void
    {
        $editedMessage = $update->editedMessage;

        if (!$editedMessage->hasLocation()) {
            return;
        }

        if (self::getThrottle()->isThrottled($gameRecord->inlineQueryId)) {
            return;
        }

        $gameManager = new GameManager();

        if (!$gameManager->isPlayerInGame($gameRecord->gameId, $editedMessage->from->id)) {
            return;
        }

        $location = $gameManager->setLocation($gameRecord->gameId, $editedMessage->location->latitude, $editedMessage->location->longitude);
        $this->logUserAction($editedMessage->from, 'update_live_location', "gameId=$gameRecord->gameId;location=$location");

        self::getThrottle()->touch($gameRecord->inlineQueryId);
        $this->refreshInlineMessage($gameRecord->inlineMessageId);
    }

    private static function getThrottle(): LocationUpdateThrottle
    {
        return self::$throttle ??= new LocationUpdateThrottle();
    }
}
