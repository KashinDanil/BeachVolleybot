<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\LeaveResult;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\InlineMessageRefresher;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class LeaveProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $inlineMessageId = $callbackQuery->inlineMessageId;

        $gameManager = new GameManager();
        $gameId = $gameManager->resolveGameIdByInlineMessageId($inlineMessageId);

        if (null === $gameId) {
            $this->bot->answerCallbackQuery($callbackQuery->id, CallbackAnswer::GAME_NOT_FOUND);

            return;
        }

        $result = $gameManager->leaveGame($gameId, $callbackQuery->from->id);

        $callbackAnswer = match ($result) {
            LeaveResult::Left => CallbackAnswer::LEFT,
            LeaveResult::NotJoined => CallbackAnswer::NOT_JOINED,
        };

        if (LeaveResult::Left === $result) {
            new InlineMessageRefresher($this->bot)->refresh($inlineMessageId);
        }

        $this->bot->answerCallbackQuery($callbackQuery->id, $callbackAnswer);
    }
}
