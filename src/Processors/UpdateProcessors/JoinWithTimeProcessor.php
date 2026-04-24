<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class JoinWithTimeProcessor extends AbstractGameReplyProcessor
{
    protected function handle(TelegramUpdate $update, GameRecord $gameRecord): void
    {
        $message = $update->message;
        $from = $message->from;

        $time = TimeExtractor::extract($message->text ?? '');

        if (null === $time) {
            return;
        }

        new GameManager()->setPlayerTime(
            $gameRecord->gameId,
            $from->id,
            $from->firstName,
            $from->lastName,
            $from->username,
            $time,
        );
        $this->logUserAction($from, 'join_with_time', "gameId=$gameRecord->gameId;time=$time");

        $this->refreshInlineMessage($gameRecord->inlineMessageId);
        $this->deleteMessage($message);
    }
}
