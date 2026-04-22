<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\NewGameData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Weather\WeatherEnqueuer;

class CreateGameProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $result = $update->chosenInlineResult;

        $gameId = new GameManager()->createGame(NewGameData::fromUser($result->from, $result->query, $result->resultId, $result->inlineMessageId));
        $this->logUserAction($result->from, 'create_game', "gameId=$gameId;query=$result->query");
        new WeatherEnqueuer()->enqueue($gameId);
    }
}
