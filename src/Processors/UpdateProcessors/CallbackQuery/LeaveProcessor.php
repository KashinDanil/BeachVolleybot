<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\LeaveResult;
use BeachVolleybot\Processors\UpdateProcessors\AbstractCallbackProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class LeaveProcessor extends AbstractCallbackProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $inlineMessageId = $callbackQuery->inlineMessageId;

        $gameManager = new GameManager();
        $gameId = $gameManager->resolveGameIdByInlineMessageId($inlineMessageId);

        if (null === $gameId) {
            $this->telegramSender->removeInlineKeyboard($inlineMessageId);
            $this->answerCallbackQuery($callbackQuery, CallbackAnswer::GAME_NOT_FOUND);

            return;
        }

        $this->logUserAction($callbackQuery->from, 'leave', "gameId=$gameId");
        $result = $gameManager->leaveGame($gameId, $callbackQuery->from->id);

        $callbackAnswer = match ($result) {
            LeaveResult::Left => CallbackAnswer::LEFT,
            LeaveResult::NotJoined => CallbackAnswer::NOT_JOINED,
        };

        if (LeaveResult::Left === $result) {
            $this->refreshInlineMessage($inlineMessageId);
        }

        $this->answerCallbackQuery($callbackQuery, $callbackAnswer);
    }
}
