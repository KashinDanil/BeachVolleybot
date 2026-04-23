<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class JoinProcessor extends AbstractGameCallbackProcessor
{
    protected function handle(TelegramUpdate $update, GameInterface $game): void
    {
        $callbackQuery = $update->callbackQuery;
        $from = $callbackQuery->from;
        $gameId = $game->getGameId();

        new GameManager()->joinGame($gameId, $from->id, $from->firstName, $from->lastName, $from->username);
        $this->logUserAction($from, 'join', "gameId=$gameId");

        $this->refreshInlineMessage($callbackQuery->inlineMessageId);
        $this->answerCallbackQuery($callbackQuery, CallbackAnswer::JOINED);
    }
}
