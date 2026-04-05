<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GamePlayerRepository;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Database\PlayerRepository;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\InlineMessageRefresher;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class SignUpProcessor extends AbstractActionProcessor
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

        new PlayerRepository($db)->upsert($from->id, $from->firstName, $from->lastName, $from->username);

        if (null === new GamePlayerRepository($db)->findByGamePlayer($gameId, $from->id)) {
            new GamePlayerRepository($db)->create($gameId, $from->id);
        }

        $slotRepo = new GameSlotRepository($db);
        $slotRepo->create($gameId, $from->id, $slotRepo->getNextPosition($gameId));

        $this->bot->answerCallbackQuery($callbackQuery->id, CallbackAnswer::SIGNED_UP);
        new InlineMessageRefresher($this->bot)->refresh($inlineMessageId);
    }
}
