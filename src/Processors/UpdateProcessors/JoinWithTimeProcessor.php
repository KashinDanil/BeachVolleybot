<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Telegram\CallbackData\CallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class JoinWithTimeProcessor extends AbstractActionReplyProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;
        $from = $message->from;

        if (!$message->hasReplyToMessage()) {
            return;
        }

        $time = TimeExtractor::extract($message->text ?? '');
        if (null === $time) {
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

        $gameManager->setPlayerTime(
            $gameLookup->gameId,
            $from->id,
            $from->firstName,
            $from->lastName,
            $from->username,
            $time,
        );
        $this->logUserAction($from, 'join_with_time', "gameId=$gameLookup->gameId;time=$time");

        $this->refreshInlineMessage($gameLookup->inlineMessageId);
        $this->deleteMessage($message);
    }
}
