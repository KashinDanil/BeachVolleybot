<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GamePlayerRepository;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Database\PlayerRepository;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class CreateGameProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $result = $update->chosenInlineResult;
        $from = $result->from;
        $db = Connection::get();

        new PlayerRepository($db)->upsert($from->id, $from->firstName, $from->lastName, $from->username);

        $gameId = new GameRepository($db)->create(
            $result->query,
            $from->id,
            $result->inlineMessageId,
            $result->resultId,
        );

        new GamePlayerRepository($db)->create($gameId, $from->id, volleyball: 1, net: 1);

        new GameSlotRepository($db)->create($gameId, $from->id, 1);

        new InlineMessageRefresher($this->bot)->refresh($result->inlineMessageId);
    }
}
