<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GamePlayerRepository;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\InlineMessageRefresher;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class RemoveVolleyballProcessor extends AbstractActionProcessor
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

        $gamePlayerRepo = new GamePlayerRepository($db);
        $volleyballCount = $gamePlayerRepo->findVolleyballCount($gameId, $from->id);

        if (null === $volleyballCount) {
            $this->bot->answerCallbackQuery($callbackQuery->id, CallbackAnswer::SIGN_UP_FIRST);

            return;
        }

        if (0 === $volleyballCount) {
            $this->bot->answerCallbackQuery($callbackQuery->id, CallbackAnswer::NO_VOLLEYBALLS);

            return;
        }

        $gamePlayerRepo->decrementVolleyball($gameId, $from->id);

        $this->bot->answerCallbackQuery($callbackQuery->id, CallbackAnswer::VOLLEYBALL_REMOVED);
        new InlineMessageRefresher($this->bot)->refresh($inlineMessageId);
    }
}
