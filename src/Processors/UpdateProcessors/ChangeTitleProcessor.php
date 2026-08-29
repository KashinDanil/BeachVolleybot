<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class ChangeTitleProcessor extends AbstractGameReplyProcessor
{
    protected function handle(TelegramUpdate $update, GameRecord $gameRecord): void
    {
        $message = $update->message;
        $from = $message->from;
        $newTitle = $message->text ?? '';

        new GameManager()->changeTitle(
            $gameRecord->gameId,
            $from->id,
            $from->firstName,
            $from->lastName,
            $from->username,
            $newTitle,
        );
        $this->logUserAction($from, 'change_title', "gameId=$gameRecord->gameId;newTitle=$newTitle");

        $this->refreshGameMessages($gameRecord->gameId);
        $this->deleteMessage($message);
    }
}
