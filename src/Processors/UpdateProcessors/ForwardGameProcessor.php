<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Common\Extractors\ForwardGameQueryExtractor;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\User\CurrentUser;
use BeachVolleybot\Validator\Rules\GameCreatorOrAdminRule;
use BeachVolleybot\Validator\Validator;

class ForwardGameProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $result = $update->chosenInlineResult;
        $gameId = ForwardGameQueryExtractor::extract($result->query);

        if (null === $gameId) {
            return;
        }

        $gameManager = new GameManager();
        $gameRecord = $gameManager->findGameRecordById($gameId);

        if (null === $gameRecord) {
            return;
        }

        $currentUser = CurrentUser::fromTelegramId($result->from->id);
        $validationState = new Validator(
            [
                new GameCreatorOrAdminRule(
                    $result->from->id,
                    $gameRecord->createdBy,
                    $currentUser->isAdmin(),
                ),
            ]
        )->validate();

        if (!$validationState->isSuccess()) {
            return;
        }

        $gameManager->addInlineMessage($gameId, $result->inlineMessageId);
        $this->logUserAction($result->from, 'forward_game', "gameId=$gameId");
    }
}
