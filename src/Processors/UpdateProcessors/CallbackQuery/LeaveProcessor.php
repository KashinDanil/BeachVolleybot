<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\LeaveResult;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class LeaveProcessor extends AbstractGameCallbackProcessor
{
    protected function handle(TelegramUpdate $update, GameInterface $game): void
    {
        $callbackQuery = $update->callbackQuery;
        $gameId = $game->getGameId();

        $result = new GameManager()->leaveGame($gameId, $callbackQuery->from->id);
        $this->logUserAction($callbackQuery->from, 'leave', "gameId=$gameId");

        $callbackAnswer = match ($result) {
            LeaveResult::Left => CallbackAnswer::LEFT,
            LeaveResult::NotJoined => CallbackAnswer::NOT_JOINED,
        };

        if (LeaveResult::Left === $result) {
            $this->refreshInlineMessage($callbackQuery->inlineMessageId);
        }

        $this->answerCallbackQuery($callbackQuery, $callbackAnswer);
    }
}
