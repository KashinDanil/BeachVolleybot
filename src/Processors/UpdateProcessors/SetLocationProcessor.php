<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class SetLocationProcessor extends AbstractGameReplyProcessor
{
    protected function handle(TelegramUpdate $update, GameRecord $gameRecord): void
    {
        $message = $update->message;

        if (!$message->hasLocation()) {
            return;
        }

        $gameManager = new GameManager();

        if (!$gameManager->isPlayerInGame($gameRecord->gameId, $message->from->id)) {
            return;
        }

        $location = $gameManager->setLocation($gameRecord->gameId, $message->location->latitude, $message->location->longitude);
        $this->logUserAction($message->from, 'set_location', "gameId=$gameRecord->gameId;location=$location");

        $this->reactWithCheckmark($message);
        $this->refreshInlineMessage($gameRecord->inlineMessageId);
    }
}
