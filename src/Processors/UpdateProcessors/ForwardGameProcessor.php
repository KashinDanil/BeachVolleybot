<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Common\Extractors\ForwardGameQueryExtractor;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Validator\Rules\GameCreatorOnlyRule;
use BeachVolleybot\Validator\Validator;

class ForwardGameProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $result = $update->chosenInlineResult;
        $gameId = ForwardGameQueryExtractor::extract($result->query, Translator::fromUser($result->from));

        if (null === $gameId) {
            return;
        }

        $gameManager = new GameManager();
        $gameRecord = $gameManager->findGameRecordById($gameId);

        if (null === $gameRecord) {
            return;
        }

        $validationState = new Validator(
            [
                new GameCreatorOnlyRule($result->from->id, $gameRecord->createdBy),
            ]
        )->validate();

        if (!$validationState->isSuccess()) {
            return;
        }

        $gameManager->addInlineMessage($gameId, $result->inlineMessageId);
        $this->logUserAction($result->from, 'forward_game', "gameId=$gameId");
    }
}
