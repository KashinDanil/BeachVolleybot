<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\NewGameData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Weather\Queue\WeatherEnqueuer;

class CreateGameProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $result = $update->chosenInlineResult;

        $newGameData = NewGameData::fromUser($result->from, $result->query, $result->resultId);
        $gameManager = new GameManager();
        $gameId = $gameManager->createGame($newGameData);
        $gameManager->addInlineMessage($gameId, $result->inlineMessageId);
        $this->logUserAction($result->from, 'create_game', "gameId=$gameId;query=$result->query");
        new WeatherEnqueuer()->enqueue($gameId);
    }
}
