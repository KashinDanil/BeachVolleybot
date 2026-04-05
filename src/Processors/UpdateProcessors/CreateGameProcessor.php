<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Game\GameFactory;
use BeachVolleybot\Game\NewGameData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class CreateGameProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $result = $update->chosenInlineResult;

        GameFactory::create(NewGameData::fromUser($result->from, $result->query, $result->resultId, $result->inlineMessageId),);

        new InlineMessageRefresher($this->bot)->refresh($result->inlineMessageId);
    }
}
