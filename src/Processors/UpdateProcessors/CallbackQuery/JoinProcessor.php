<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Processors\UpdateProcessors\AbstractCallbackProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class JoinProcessor extends AbstractCallbackProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $from = $callbackQuery->from;
        $inlineMessageId = $callbackQuery->inlineMessageId;

        $gameManager = new GameManager();
        $gameId = $gameManager->resolveGameIdByInlineMessageId($inlineMessageId);

        if (null === $gameId) {
            $this->telegramSender->removeInlineKeyboard($inlineMessageId);
            $this->answerCallbackQuery($callbackQuery, CallbackAnswer::GAME_NOT_FOUND);

            return;
        }

        $gameManager->joinGame($gameId, $from->id, $from->firstName, $from->lastName, $from->username);
        $this->logUserAction($from, 'join', "gameId=$gameId");

        $this->refreshInlineMessage($inlineMessageId);
        $this->answerCallbackQuery($callbackQuery, CallbackAnswer::JOINED);
    }
}
