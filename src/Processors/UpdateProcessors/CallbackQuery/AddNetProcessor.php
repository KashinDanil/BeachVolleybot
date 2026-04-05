<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GamePlayerRepository;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\InlineMessageRefresher;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class AddNetProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $from = $callbackQuery->from;
        $inlineMessageId = $callbackQuery->inlineMessageId;
        $db = Connection::get();

        $gameId = new GameRepository($db)->findGameIdByInlineMessageId($inlineMessageId);

        if (null === $gameId) {
            $this->bot->answerCallbackQuery($callbackQuery->id, CallbackAnswer::GAME_NOT_FOUND);

            return;
        }

        $updated = new GamePlayerRepository($db)->incrementNet($gameId, $from->id);

        if (!$updated) {
            $this->bot->answerCallbackQuery($callbackQuery->id, CallbackAnswer::SIGN_UP_FIRST);

            return;
        }

        $this->bot->answerCallbackQuery($callbackQuery->id, CallbackAnswer::NET_ADDED);
        new InlineMessageRefresher($this->bot)->refresh($inlineMessageId);
    }
}
