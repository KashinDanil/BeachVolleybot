<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameMessageManager;
use BeachVolleybot\Game\NewGameData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Weather\Queue\WeatherEnqueuer;

class CreateGameProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $result = $update->chosenInlineResult;

        $newGameData = NewGameData::fromUser($result->from, $result->query, $result->resultId);
        $gameId = new GameManager()->createGame($newGameData);
        new GameMessageManager()->addInlineMessage($gameId, $result->inlineMessageId, $result->resultId);
        $this->logUserAction($result->from, 'create_game', "gameId=$gameId;query=$result->query");
        new WeatherEnqueuer()->enqueueForGameId($gameId);
    }
}
