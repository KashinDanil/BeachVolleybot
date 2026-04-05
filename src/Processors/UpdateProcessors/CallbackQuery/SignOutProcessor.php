<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GamePlayerRepository;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\InlineMessageRefresher;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class SignOutProcessor extends AbstractActionProcessor
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

        $slotRepo = new GameSlotRepository($db);
        $positions = $slotRepo->findPositionsByPlayer($gameId, $from->id);

        if (empty($positions)) {
            $this->bot->answerCallbackQuery($callbackQuery->id, CallbackAnswer::NOT_SIGNED_UP);

            return;
        }

        $slotRepo->delete($gameId, max($positions));

        if (1 === count($positions)) {
            new GamePlayerRepository($db)->delete($gameId, $from->id);
        }

        $this->bot->answerCallbackQuery($callbackQuery->id, CallbackAnswer::SIGNED_OUT);
        new InlineMessageRefresher($this->bot)->refresh($inlineMessageId);
    }
}
